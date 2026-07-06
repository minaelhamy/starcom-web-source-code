<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CreditApplicationStatus;
use App\Enums\CreditFacilityStatus;
use App\Enums\Role as EnumRole;
use Exception;
use Illuminate\Http\Request;
use App\Libraries\AppLibrary;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use App\Services\CreditApplicationService;
use App\Services\ProductService;
use App\Services\DashboardService;
use App\Http\Resources\UserResource;
use App\Support\StarcomIntelligenceCalculator;
use App\Http\Resources\OrderSummaryResource;
use App\Http\Resources\SalesSummaryResource;
use App\Http\Resources\SimpleProductResource;
use App\Http\Resources\CustomerStatesResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\OrderStatisticsResource;
use Illuminate\Routing\Controllers\HasMiddleware;

class DashboardController extends AdminController implements HasMiddleware
{
    private DashboardService $dashboardService;
    private ProductService $productService;
    private CreditApplicationService $creditApplicationService;

    public function __construct(DashboardService $dashboardService, ProductService $productService, CreditApplicationService $creditApplicationService)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
        $this->productService = $productService;
        $this->creditApplicationService = $creditApplicationService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard', only: ['orderStatistics']),
            new Middleware('permission:dashboard', only: ['orderSummary']),
            new Middleware('permission:dashboard', only: ['featuredItems']),
            new Middleware('permission:dashboard', only: ['topCustomers']),
            new Middleware('permission:dashboard', only: ['totalSales']),
            new Middleware('permission:dashboard', only: ['salesSummary']),
            new Middleware('permission:dashboard', only: ['customerStates']),
            new Middleware('permission:dashboard', only: ['totalOrders']),
            new Middleware('permission:dashboard', only: ['totalCustomers']),
            new Middleware('permission:dashboard', only: ['totalProducts']),
            new Middleware('permission:dashboard', only: ['adminCreditFacilitiesSummary']),
        ];
    }

    public function totalSales(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_sales' => AppLibrary::currencyAmountFormat($this->dashboardService->totalSales())]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function totalOrders(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_orders' => $this->dashboardService->totalOrders()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function totalCustomers(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_customers' => $this->dashboardService->totalCustomers()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function totalProducts(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_products' => $this->dashboardService->totalProducts()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function orderStatistics(
        Request $request
    ): \Illuminate\Http\Response | OrderStatisticsResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return new OrderStatisticsResource($this->dashboardService->orderStatistics($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function salesSummary(
        Request $request
    ): \Illuminate\Http\Response | SalesSummaryResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return new SalesSummaryResource($this->dashboardService->salesSummary($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function orderSummary(
        Request $request
    ): \Illuminate\Http\Response | OrderSummaryResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return new OrderSummaryResource($this->dashboardService->orderSummary($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function customerStates(
        Request $request
    ): \Illuminate\Http\Response | CustomerStatesResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return new CustomerStatesResource($this->dashboardService->customerStates($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function topCustomers(): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return UserResource::collection($this->dashboardService->topCustomers());
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function topProducts(): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return SimpleProductResource::collection($this->productService->topProducts());
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function lenderSummary(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $actor = Auth::user();

            if (!$actor || !$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
                return response(['status' => false, 'message' => trans('all.message.permission_denied')], 403);
            }

            $lenderQueueQuery = $this->creditApplicationService->lenderOpportunitiesQuery($actor);
            $opportunitiesQuery = $this->creditApplicationService->lenderFreshOpportunitiesQuery($actor);
            $pendingApprovalQuery = $this->creditApplicationService->lenderPendingApprovalQuery($actor);

            $approvedFacilitiesQuery = $this->creditApplicationService->portfolioQuery($actor)
                ->where('status', CreditFacilityStatus::APPROVED);

            $reviewedFacilitiesQuery = $this->creditApplicationService->portfolioQuery($actor);

            $opportunitiesCount = (clone $opportunitiesQuery)->count();
            $pendingApprovalCount = (clone $pendingApprovalQuery)->count();
            $approvedAmount = (float)(clone $approvedFacilitiesQuery)->sum('approved_amount');
            $availableAmount = (float)(clone $approvedFacilitiesQuery)->sum('available_amount');
            $utilizedAmount = (float)(clone $approvedFacilitiesQuery)->sum('utilized_amount');
            $activeCustomersCount = (clone $approvedFacilitiesQuery)->distinct('user_id')->count('user_id');
            $activeFacilitiesCount = (clone $approvedFacilitiesQuery)->count();
            $reviewedRequestsCount = (clone $reviewedFacilitiesQuery)->count();
            $acceptedRequestsCount = (clone $reviewedFacilitiesQuery)
                ->where('status', CreditFacilityStatus::APPROVED)
                ->count();
            $declinedRequestsCount = (clone $reviewedFacilitiesQuery)
                ->where('status', CreditFacilityStatus::DECLINED)
                ->count();

            $utilizationRate = $approvedAmount > 0 ? round(($utilizedAmount / $approvedAmount) * 100, 2) : 0;

            $bestPerformingCustomers = (clone $approvedFacilitiesQuery)
                ->get()
                ->map(function (CreditFacility $facility) {
                    $intelligence = StarcomIntelligenceCalculator::forUser($facility->user);

                    return [
                        'facility_id'                      => $facility->id,
                        'customer_id'                      => $facility->user?->id,
                        'customer_name'                    => $facility->user?->name,
                        'customer_phone'                   => trim(($facility->user?->country_code ?: '') . ' ' . ($facility->user?->phone ?: '')),
                        'customer_address'                 => $facility->user?->address,
                        'approved_amount'                  => (float)$facility->approved_amount,
                        'approved_amount_currency'         => AppLibrary::currencyAmountFormat($facility->approved_amount),
                        'available_amount'                 => (float)$facility->available_amount,
                        'available_amount_currency'        => AppLibrary::currencyAmountFormat($facility->available_amount),
                        'utilized_amount'                  => (float)$facility->utilized_amount,
                        'utilized_amount_currency'         => AppLibrary::currencyAmountFormat($facility->utilized_amount),
                        'total_monthly_purchase'           => (float)($intelligence['average_monthly_purchase_last_12_months'] ?? 0),
                        'total_monthly_purchase_currency'  => $intelligence['average_monthly_purchase_last_12_months_currency'] ?? AppLibrary::currencyAmountFormat(0),
                        'credit_proposed_amount'           => (float)($intelligence['average_monthly_purchase_last_12_months'] ?? 0),
                        'credit_proposed_amount_currency'  => $intelligence['average_monthly_purchase_last_12_months_currency'] ?? AppLibrary::currencyAmountFormat(0),
                    ];
                })
                ->sortByDesc(function (array $customer) {
                    return [$customer['total_monthly_purchase'], $customer['utilized_amount'], $customer['approved_amount']];
                })
                ->take(5)
                ->values();

            $recentOpportunities = (clone $opportunitiesQuery)
                ->latest()
                ->take(5)
                ->get()
                ->map(function (CreditApplication $application) {
                    $intelligence = StarcomIntelligenceCalculator::forUser($application->user);

                    return [
                        'application_id'                   => $application->id,
                        'customer_id'                      => $application->user?->id,
                        'customer_name'                    => $application->user?->name,
                        'customer_phone'                   => trim(($application->user?->country_code ?: '') . ' ' . ($application->user?->phone ?: '')),
                        'customer_address'                 => $application->user?->address,
                        'created_at'                       => $application->created_at?->toDateTimeString(),
                        'created_date'                     => $application->created_at ? AppLibrary::date($application->created_at) : null,
                        'credit_proposed_amount'           => (float)($intelligence['average_monthly_purchase_last_12_months'] ?? 0),
                        'credit_proposed_amount_currency'  => $intelligence['average_monthly_purchase_last_12_months_currency'] ?? AppLibrary::currencyAmountFormat(0),
                    ];
                })
                ->values();

            return [
                'data' => [
                    'opportunities_count'                => $opportunitiesCount,
                    'pending_approval_count'             => $pendingApprovalCount,
                    'active_customers_count'             => $activeCustomersCount,
                    'active_facilities_count'            => $activeFacilitiesCount,
                    'reviewed_requests_count'            => $reviewedRequestsCount,
                    'accepted_requests_count'            => $acceptedRequestsCount,
                    'declined_requests_count'            => $declinedRequestsCount,
                    'wallet_value'                       => $approvedAmount,
                    'wallet_value_currency'              => AppLibrary::currencyAmountFormat($approvedAmount),
                    'available_wallet_value'             => $availableAmount,
                    'available_wallet_value_currency'    => AppLibrary::currencyAmountFormat($availableAmount),
                    'utilized_wallet_value'              => $utilizedAmount,
                    'utilized_wallet_value_currency'     => AppLibrary::currencyAmountFormat($utilizedAmount),
                    'utilization_rate'                   => $utilizationRate,
                    'best_performing_customers'          => $bestPerformingCustomers,
                    'recent_opportunities'               => $recentOpportunities,
                ],
            ];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function adminCreditFacilitiesSummary(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $actor = Auth::user();

            if (!$actor || !$actor->hasRole(EnumRole::ADMIN)) {
                return response(['status' => false, 'message' => trans('all.message.permission_denied')], 403);
            }

            $opportunitiesQuery = CreditApplication::with('user')
                ->where('status', CreditApplicationStatus::PENDING)
                ->whereDoesntHave('facilities', function ($facilityQuery) {
                    $facilityQuery->where('status', CreditFacilityStatus::APPROVED);
                });

            $approvedFacilitiesQuery = CreditFacility::with(['user', 'institution.financialInstitutionProfile', 'employee'])
                ->where('status', CreditFacilityStatus::APPROVED);

            $reviewedFacilitiesQuery = CreditFacility::with(['institution.financialInstitutionProfile', 'employee']);

            $opportunitiesCount = (clone $opportunitiesQuery)->count();
            $approvedAmount = (float)(clone $approvedFacilitiesQuery)->sum('approved_amount');
            $availableAmount = (float)(clone $approvedFacilitiesQuery)->sum('available_amount');
            $utilizedAmount = (float)(clone $approvedFacilitiesQuery)->sum('utilized_amount');
            $activeCustomersCount = (clone $approvedFacilitiesQuery)->distinct('user_id')->count('user_id');
            $approvedFacilitiesCount = (clone $approvedFacilitiesQuery)->count();
            $reviewedRequestsCount = (clone $reviewedFacilitiesQuery)->count();
            $acceptedRequestsCount = (clone $reviewedFacilitiesQuery)->where('status', CreditFacilityStatus::APPROVED)->count();
            $declinedRequestsCount = (clone $reviewedFacilitiesQuery)->where('status', CreditFacilityStatus::DECLINED)->count();
            $expiredFacilitiesCount = (clone $reviewedFacilitiesQuery)->where('status', CreditFacilityStatus::EXPIRED)->count();
            $institutionsCount = (clone $reviewedFacilitiesQuery)->distinct('financial_institution_user_id')->count('financial_institution_user_id');
            $employeesCount = (clone $reviewedFacilitiesQuery)
                ->whereNotNull('financial_institution_employee_user_id')
                ->distinct('financial_institution_employee_user_id')
                ->count('financial_institution_employee_user_id');

            $utilizationRate = $approvedAmount > 0 ? round(($utilizedAmount / $approvedAmount) * 100, 2) : 0;

            $topInstitutions = CreditFacility::with(['institution.financialInstitutionProfile'])
                ->where('status', CreditFacilityStatus::APPROVED)
                ->get()
                ->groupBy('financial_institution_user_id')
                ->map(function ($facilities) {
                    /** @var CreditFacility $firstFacility */
                    $firstFacility = $facilities->first();

                    return [
                        'institution_id'                => $firstFacility?->institution?->id,
                        'institution_name'              => $firstFacility?->institution?->name,
                        'institution_company_name'      => $firstFacility?->institution?->financialInstitutionProfile?->company_name ?: $firstFacility?->institution?->name,
                        'approved_facilities_count'     => $facilities->count(),
                        'active_customers_count'        => $facilities->pluck('user_id')->unique()->count(),
                        'approved_amount'               => (float)$facilities->sum('approved_amount'),
                        'approved_amount_currency'      => AppLibrary::currencyAmountFormat($facilities->sum('approved_amount')),
                        'available_amount'              => (float)$facilities->sum('available_amount'),
                        'available_amount_currency'     => AppLibrary::currencyAmountFormat($facilities->sum('available_amount')),
                        'utilized_amount'               => (float)$facilities->sum('utilized_amount'),
                        'utilized_amount_currency'      => AppLibrary::currencyAmountFormat($facilities->sum('utilized_amount')),
                    ];
                })
                ->sortByDesc(function (array $institution) {
                    return [$institution['utilized_amount'], $institution['approved_amount'], $institution['active_customers_count']];
                })
                ->take(5)
                ->values();

            $latestApprovedClients = (clone $approvedFacilitiesQuery)
                ->latest('reviewed_at')
                ->take(5)
                ->get()
                ->map(function (CreditFacility $facility) {
                    return [
                        'facility_id'                  => $facility->id,
                        'customer_id'                  => $facility->user?->id,
                        'customer_name'                => $facility->user?->name,
                        'customer_phone'               => trim(($facility->user?->country_code ?: '') . ' ' . ($facility->user?->phone ?: '')),
                        'customer_address'             => $facility->user?->address,
                        'institution_name'             => $facility->institution?->financialInstitutionProfile?->company_name ?: $facility->institution?->name,
                        'employee_name'                => $facility->employee?->name ?: $facility->institution?->name,
                        'approved_amount'              => (float)$facility->approved_amount,
                        'approved_amount_currency'     => AppLibrary::currencyAmountFormat($facility->approved_amount),
                        'available_amount'             => (float)$facility->available_amount,
                        'available_amount_currency'    => AppLibrary::currencyAmountFormat($facility->available_amount),
                        'utilized_amount'              => (float)$facility->utilized_amount,
                        'utilized_amount_currency'     => AppLibrary::currencyAmountFormat($facility->utilized_amount),
                        'due_at'                       => $facility->due_at ? $facility->due_at->toDateString() : null,
                        'reviewed_at'                  => $facility->reviewed_at ? $facility->reviewed_at->toDateTimeString() : null,
                        'status'                       => $facility->status,
                    ];
                })
                ->values();

            $recentOpportunities = (clone $opportunitiesQuery)
                ->latest()
                ->take(5)
                ->get()
                ->map(function (CreditApplication $application) {
                    $intelligence = StarcomIntelligenceCalculator::forUser($application->user);

                    return [
                        'application_id'                                  => $application->id,
                        'customer_name'                                   => $application->user?->name,
                        'customer_phone'                                  => trim(($application->user?->country_code ?: '') . ' ' . ($application->user?->phone ?: '')),
                        'customer_address'                                => $application->user?->address,
                        'created_date'                                    => $application->created_at ? AppLibrary::date($application->created_at) : null,
                        'average_monthly_purchase_last_12_months'         => (float)($intelligence['average_monthly_purchase_last_12_months'] ?? 0),
                        'average_monthly_purchase_last_12_months_currency'=> $intelligence['average_monthly_purchase_last_12_months_currency'] ?? AppLibrary::currencyAmountFormat(0),
                    ];
                })
                ->values();

            return [
                'data' => [
                    'opportunities_count'             => $opportunitiesCount,
                    'active_customers_count'          => $activeCustomersCount,
                    'approved_facilities_count'       => $approvedFacilitiesCount,
                    'reviewed_requests_count'         => $reviewedRequestsCount,
                    'accepted_requests_count'         => $acceptedRequestsCount,
                    'declined_requests_count'         => $declinedRequestsCount,
                    'expired_facilities_count'        => $expiredFacilitiesCount,
                    'institutions_count'              => $institutionsCount,
                    'employees_count'                 => $employeesCount,
                    'wallet_value'                    => $approvedAmount,
                    'wallet_value_currency'           => AppLibrary::currencyAmountFormat($approvedAmount),
                    'available_wallet_value'          => $availableAmount,
                    'available_wallet_value_currency' => AppLibrary::currencyAmountFormat($availableAmount),
                    'utilized_wallet_value'           => $utilizedAmount,
                    'utilized_wallet_value_currency'  => AppLibrary::currencyAmountFormat($utilizedAmount),
                    'utilization_rate'                => $utilizationRate,
                    'top_institutions'                => $topInstitutions,
                    'latest_approved_clients'         => $latestApprovedClients,
                    'recent_opportunities'            => $recentOpportunities,
                ],
            ];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
