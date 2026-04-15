<?php

namespace App\Services;

use App\Enums\CreditApplicationStatus;
use App\Enums\CreditFacilityStatus;
use App\Enums\Role as EnumRole;
use App\Http\Requests\CreditApplicationDecisionRequest;
use App\Http\Requests\CreditApplicationStoreRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use App\Models\User;
use App\Notifications\CreditApplicationApprovedNotification;
use App\Notifications\CreditApplicationDeclinedNotification;
use App\Notifications\NewCreditApplicationSubmittedNotification;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreditApplicationService
{
    public function customerList(PaginateRequest $request)
    {
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';

        return CreditApplication::with(['user', 'facilities.institution.financialInstitutionProfile'])
            ->where('user_id', Auth::id())
            ->latest()
            ->$method($methodValue);
    }

    public function customerStore(CreditApplicationStoreRequest $request): CreditApplication
    {
        try {
            if (CreditApplication::where('user_id', Auth::id())->where('status', CreditApplicationStatus::PENDING)->exists()) {
                throw new Exception(trans('all.message.credit_application_pending_exists'), 422);
            }

            $application = CreditApplication::create([
                'user_id' => Auth::id(),
                'status'  => CreditApplicationStatus::PENDING,
                'notes'   => $request->notes,
            ]);

            $application->addMediaFromRequest('national_id_document')->toMediaCollection('national_id_document');
            $application->addMediaFromRequest('commercial_register_document')->toMediaCollection('commercial_register_document');
            if ($request->hasFile('tax_card_document')) {
                $application->addMediaFromRequest('tax_card_document')->toMediaCollection('tax_card_document');
            }

            User::role(EnumRole::FINANCIAL_INSTITUTION)->get()->each(function (User $institutionUser) use ($application) {
                $this->safeNotify($institutionUser, new NewCreditApplicationSubmittedNotification($application));
            });

            return $application->load(['user', 'facilities.institution.financialInstitutionProfile']);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function queueList(PaginateRequest $request)
    {
        $actor = Auth::user();
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';

        return CreditApplication::with(['user', 'facilities.institution.financialInstitutionProfile'])
            ->where(function ($query) use ($actor) {
                if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
                    $query->whereDoesntHave('facilities', function ($facilityQuery) use ($actor) {
                        $facilityQuery->where('financial_institution_user_id', $actor->id);
                    });
                }
            })
            ->latest()
            ->$method($methodValue);
    }

    public function portfolioList(PaginateRequest $request)
    {
        $actor = Auth::user();
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';

        return CreditFacility::with(['user', 'application', 'institution.financialInstitutionProfile'])
            ->where(function ($query) use ($actor) {
                if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
                    $query->where('financial_institution_user_id', $actor->id);
                }
            })
            ->latest()
            ->$method($methodValue);
    }

    public function show(CreditApplication $creditApplication): CreditApplication
    {
        return $creditApplication->load(['user', 'facilities.institution.financialInstitutionProfile']);
    }

    public function approve(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) && !$actor->hasRole(EnumRole::ADMIN)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditApplication->facilities()->where('financial_institution_user_id', $actor->id)->exists()) {
                throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
            }

            $facility = app(WalletService::class)->creditByFacility(
                $creditApplication->user,
                $creditApplication,
                $actor,
                (float)$request->approved_amount,
                'تمت إضافة رصيد إلى المحفظة',
                [
                    'duration_days' => (int)$request->duration_days,
                    'notes'         => $request->notes,
                ]
            );

            $this->refreshApplicationStatus($creditApplication);
            $this->safeNotify($creditApplication->user, new CreditApplicationApprovedNotification($creditApplication, $facility));

            return $facility->load(['user', 'institution.financialInstitutionProfile', 'application']);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function decline(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) && !$actor->hasRole(EnumRole::ADMIN)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditApplication->facilities()->where('financial_institution_user_id', $actor->id)->exists()) {
                throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
            }

            $facility = CreditFacility::create([
                'credit_application_id'         => $creditApplication->id,
                'user_id'                       => $creditApplication->user_id,
                'financial_institution_user_id' => $actor->id,
                'status'                        => CreditFacilityStatus::DECLINED,
                'approved_amount'               => 0,
                'available_amount'              => 0,
                'utilized_amount'               => 0,
                'duration_days'                 => 30,
                'reviewed_at'                   => now(),
                'notes'                         => $request->decline_reason ?: $request->notes,
            ]);

            $this->refreshApplicationStatus($creditApplication);
            $this->safeNotify($creditApplication->user, new CreditApplicationDeclinedNotification($creditApplication, $facility));

            return $facility->load(['user', 'institution.financialInstitutionProfile', 'application']);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function summaryForCustomer(User $user): array
    {
        $approvedFacilities = CreditFacility::where('user_id', $user->id)->where('status', CreditFacilityStatus::APPROVED)->get();

        return [
            'wallet_balance'             => (float)$user->balance,
            'wallet_balance_currency'    => AppLibrary::currencyAmountFormat($user->balance),
            'total_credit_limit'         => (float)$approvedFacilities->sum('approved_amount'),
            'total_available_credit'     => (float)$approvedFacilities->sum('available_amount'),
            'total_utilized_credit'      => (float)$approvedFacilities->sum('utilized_amount'),
            'active_facilities'          => $approvedFacilities->count(),
        ];
    }

    protected function refreshApplicationStatus(CreditApplication $creditApplication): void
    {
        $creditApplication->refresh();
        $facilities = $creditApplication->facilities;

        if ($facilities->where('status', CreditFacilityStatus::APPROVED)->count() > 0) {
            $creditApplication->status = CreditApplicationStatus::APPROVED;
        } elseif ($facilities->count() > 0 && $facilities->where('status', CreditFacilityStatus::DECLINED)->count() === $facilities->count()) {
            $creditApplication->status = CreditApplicationStatus::DECLINED;
        } else {
            $creditApplication->status = CreditApplicationStatus::PENDING;
        }

        $creditApplication->save();
    }

    protected function safeNotify(User $user, object $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $throwable) {
            Log::warning('Credit notification failed', [
                'user_id' => $user->id,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
