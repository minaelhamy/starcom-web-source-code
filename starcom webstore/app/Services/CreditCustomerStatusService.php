<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditCustomerStatusService
{
    public function list(Request $request)
    {
        $query = $this->baseQuery($request);

        return (int) $request->get('paginate', 1) === 1
            ? $query->paginate(max(1, (int) $request->get('per_page', 25)))
            : $query->get();
    }

    public function summary(): array
    {
        $query = User::query()->whereHas('creditApplications');

        return [
            'total' => (clone $query)->count(),
            'blacklisted' => (clone $query)->whereNotNull('credit_blacklisted_at')->count(),
            'top_customers' => (clone $query)->where('is_top_credit_customer', true)->count(),
        ];
    }

    public function setBlacklist(User $user, bool $blacklisted, ?string $reason): User
    {
        $this->assertCreditApplicant($user);
        $reason = trim((string) $reason);

        if ($blacklisted && $reason === '') {
            throw new Exception('يرجى كتابة سبب الإدراج في القائمة السوداء.', 422);
        }

        return DB::transaction(function () use ($user, $blacklisted, $reason) {
            $customer = User::lockForUpdate()->findOrFail($user->id);

            $customer->forceFill($blacklisted ? [
                'credit_blacklisted_at' => now(),
                'credit_blacklisted_by_user_id' => Auth::id(),
                'credit_blacklist_reason' => $reason,
                'is_top_credit_customer' => false,
                'top_credit_customer_at' => null,
                'top_credit_customer_by_user_id' => null,
            ] : [
                'credit_blacklisted_at' => null,
                'credit_blacklisted_by_user_id' => null,
                'credit_blacklist_reason' => null,
            ])->save();

            return $this->loadCustomer($customer);
        });
    }

    public function setTopCustomer(User $user, bool $topCustomer): User
    {
        $this->assertCreditApplicant($user);

        return DB::transaction(function () use ($user, $topCustomer) {
            $customer = User::lockForUpdate()->findOrFail($user->id);
            if ($topCustomer && $customer->credit_blacklisted_at) {
                throw new Exception('لا يمكن تمييز عميل مدرج في القائمة السوداء كعميل مميز.', 422);
            }

            $customer->forceFill([
                'is_top_credit_customer' => $topCustomer,
                'top_credit_customer_at' => $topCustomer ? now() : null,
                'top_credit_customer_by_user_id' => $topCustomer ? Auth::id() : null,
            ])->save();

            return $this->loadCustomer($customer);
        });
    }

    private function baseQuery(Request $request): Builder
    {
        $query = User::query()
            ->whereHas('creditApplications')
            ->with('latestCreditApplication')
            ->withCount('creditApplications');

        match ($request->get('filter')) {
            'blacklisted' => $query->whereNotNull('credit_blacklisted_at'),
            'top_customers' => $query->where('is_top_credit_customer', true),
            default => null,
        };

        $term = trim((string) $request->get('term', ''));
        if ($term !== '') {
            $query->where(function (Builder $customerQuery) use ($term) {
                $customerQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhereHas('creditApplications', function (Builder $applicationQuery) use ($term) {
                        $applicationQuery->where('full_name', 'like', "%{$term}%")
                            ->orWhere('national_id_number', 'like', "%{$term}%");
                    });
            });
        }

        return $query
            ->orderByDesc('credit_blacklisted_at')
            ->orderByDesc('is_top_credit_customer')
            ->latest('updated_at');
    }

    private function assertCreditApplicant(User $user): void
    {
        if (!$user->creditApplications()->exists()) {
            throw new Exception('لا يملك هذا العميل أي طلب تمويل.', 422);
        }
    }

    private function loadCustomer(User $customer): User
    {
        return $customer->load('latestCreditApplication')->loadCount('creditApplications');
    }
}
