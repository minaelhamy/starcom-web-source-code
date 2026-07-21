<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as EnumRole;
use App\Enums\FinancialInstitutionUserRole;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\PaymentCollectionReportResource;
use App\Models\CreditFacilityRepayment;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class PaymentCollectionReportController extends AdminController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:payment-collection-report', only: ['index']),
        ];
    }

    public function index(PaginateRequest $request): Response|AnonymousResourceCollection|Application|ResponseFactory
    {
        try {
            $actor = Auth::user();

            if (
                $actor?->hasRole(EnumRole::FINANCIAL_INSTITUTION) &&
                $actor->normalizedFinancialInstitutionRole() === FinancialInstitutionUserRole::LIMITED_EMPLOYEE
            ) {
                return response(['status' => false, 'message' => trans('all.message.permission_denied')], 403);
            }

            $paginate = (int) $request->get('paginate', 1) === 1;
            $perPage = max(1, (int) $request->get('per_page', 10));

            $query = CreditFacilityRepayment::query()
                ->with([
                    'facility.application',
                    'facility.employee',
                    'facility.institution.financialInstitutionProfile',
                    'user.latestAddress',
                    'creator.financialInstitutionOwner.financialInstitutionProfile',
                ]);

            if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
                $institutionId = (int) ($actor->resolvedFinancialInstitutionUserId() ?: $actor->id);
                $query->where('financial_institution_user_id', $institutionId);
            } else {
                $institutionUserId = $request->get('financial_institution_user_id');
                if ($institutionUserId !== null && $institutionUserId !== '') {
                    $query->where('financial_institution_user_id', (int) $institutionUserId);
                }
            }

            $employeeUserId = $request->get('financial_institution_employee_user_id');
            if ($employeeUserId !== null && $employeeUserId !== '') {
                $query->whereHas('facility', function (Builder $facilityQuery) use ($employeeUserId) {
                    $facilityQuery->where('financial_institution_employee_user_id', (int) $employeeUserId);
                });
            }

            $paymentMethod = trim((string) $request->get('payment_method', ''));
            if ($paymentMethod !== '') {
                $query->where('payment_method', 'like', '%' . $paymentMethod . '%');
            }

            $dateFrom = $request->get('date_from');
            if ($dateFrom) {
                $query->whereDate('paid_at', '>=', $dateFrom);
            }

            $dateTo = $request->get('date_to');
            if ($dateTo) {
                $query->whereDate('paid_at', '<=', $dateTo);
            }

            $term = trim((string) $request->get('term', ''));
            if ($term !== '') {
                $normalizedTerm = preg_replace('/\s+/', '', $term);

                $query->where(function (Builder $filterQuery) use ($term, $normalizedTerm) {
                    $filterQuery
                        ->where('reference_number', 'like', '%' . $term . '%')
                        ->orWhere('payment_method', 'like', '%' . $term . '%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($term, $normalizedTerm) {
                            $userQuery->where('name', 'like', '%' . $term . '%')
                                ->orWhere('phone', 'like', '%' . $normalizedTerm . '%')
                                ->orWhereRaw("REPLACE(CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')), ' ', '') LIKE ?", ['%' . $normalizedTerm . '%']);
                        })
                        ->orWhereHas('facility.application', function (Builder $applicationQuery) use ($term, $normalizedTerm) {
                            $applicationQuery->where('full_name', 'like', '%' . $term . '%')
                                ->orWhere('national_id_number', 'like', '%' . $normalizedTerm . '%');
                        })
                        ->orWhereHas('facility.institution.financialInstitutionProfile', function (Builder $institutionQuery) use ($term) {
                            $institutionQuery->where('company_name', 'like', '%' . $term . '%');
                        });
                });
            }

            $summary = [
                'total_repayments_count' => (clone $query)->count(),
                'total_repaid_amount' => (float) (clone $query)->sum('amount'),
                'total_customers_count' => (clone $query)->distinct('user_id')->count('user_id'),
                'total_institutions_count' => (clone $query)->whereNotNull('financial_institution_user_id')->distinct('financial_institution_user_id')->count('financial_institution_user_id'),
                'fully_settled_count' => (clone $query)
                    ->whereHas('facility', function (Builder $facilityQuery) {
                        $facilityQuery->where('status', 'settled');
                    })
                    ->distinct('credit_facility_id')
                    ->count('credit_facility_id'),
            ];

            $query->orderByDesc('paid_at')->orderByDesc('id');

            $results = $paginate ? $query->paginate($perPage) : $query->get();

            return PaymentCollectionReportResource::collection($results)->additional([
                'summary' => $summary,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'payment_method' => $paymentMethod,
                    'term' => $term,
                    'financial_institution_user_id' => $request->get('financial_institution_user_id'),
                    'financial_institution_employee_user_id' => $request->get('financial_institution_employee_user_id'),
                ],
            ]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
