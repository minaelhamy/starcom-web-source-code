<?php

namespace App\Services;

use App\Enums\CreditApplicationStatus;
use App\Enums\CustomerServiceLeadStatus;
use App\Enums\Role as EnumRole;
use App\Http\Requests\CustomerServiceLeadApplicationRequest;
use App\Http\Requests\CustomerServiceLeadStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use App\Models\CreditFacilityRepayment;
use App\Models\CustomerServiceLead;
use App\Models\CustomerServiceLeadActivity;
use App\Models\User;
use App\Notifications\NewCreditApplicationSubmittedNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class CustomerServiceLeadService
{
    public const TAB_ALL = 'all';
    public const TAB_CALLBACK = 'callback';
    public const TAB_WAITING = 'waiting';
    public const TAB_REFUSED = 'refused';
    public const PIPELINE_NOT_APPROACHED = 'not_approached';
    public const PIPELINE_WAITING_DOCUMENTS = 'waiting_documents';
    public const PIPELINE_READY_TO_SUBMIT = 'ready_to_submit';
    public const PIPELINE_SUBMITTED_TO_LENDER = 'submitted_to_lender';
    public const PIPELINE_PENDING_CUSTOMER_UPDATE = 'pending_customer_update';
    public const PIPELINE_APPROVED_WAITING_INVOICE = 'approved_waiting_invoice';
    public const PIPELINE_INVOICE_ISSUED = 'invoice_issued';
    public const PIPELINE_SIGNED_CONTRACTS_PENDING = 'signed_contracts_pending';
    public const PIPELINE_COLLECTION_IN_PROGRESS = 'collection_in_progress';
    public const PIPELINE_COLLECTION_COMPLETED = 'collection_completed';
    public const PIPELINE_REJECTED_BY_LENDER = 'rejected_by_lender';

    public static function statusLabels(): array
    {
        return [
            CustomerServiceLeadStatus::NOT_APPROACHED => 'لم يتم التواصل بعد',
            CustomerServiceLeadStatus::WAITING_DOCUMENTS => 'في انتظار الاوراق',
            CustomerServiceLeadStatus::DOCUMENTS_RECEIVED => 'تم استلام الاوراق',
            CustomerServiceLeadStatus::NOT_INTERESTED => 'غير مهتم',
            CustomerServiceLeadStatus::VISIT_REQUIRED => 'مطلوب زيارة',
            CustomerServiceLeadStatus::NO_ANSWER => 'لم يتم الرد',
            CustomerServiceLeadStatus::CONTACTED_WAITING_REPLY => 'تم التواصل فى انتظار الرد',
            CustomerServiceLeadStatus::CALL_LATER => 'بيكنسل هكلمه فى وقت تانى',
            CustomerServiceLeadStatus::REJECTED_COMMERCIAL_REGISTER => 'رفض فكره السجل',
            CustomerServiceLeadStatus::REVIEW_WITH_OWNER => 'هيراجع صاحبب العمل',
            CustomerServiceLeadStatus::NO_CREDIT_SALES => 'مش بيشتغل اجل',
            CustomerServiceLeadStatus::NO_REGISTER_NO_ID_CONSENT => 'معندوش سجل ومش موافق على البطاقه',
            CustomerServiceLeadStatus::CLOSED => 'مقفول',
        ];
    }

    public static function callbackStatuses(): array
    {
        return [
            CustomerServiceLeadStatus::NO_ANSWER,
            CustomerServiceLeadStatus::CONTACTED_WAITING_REPLY,
            CustomerServiceLeadStatus::CALL_LATER,
            CustomerServiceLeadStatus::REVIEW_WITH_OWNER,
        ];
    }

    public static function waitingStatuses(): array
    {
        return [
            CustomerServiceLeadStatus::WAITING_DOCUMENTS,
            CustomerServiceLeadStatus::DOCUMENTS_RECEIVED,
            CustomerServiceLeadStatus::VISIT_REQUIRED,
        ];
    }

    public static function refusedStatuses(): array
    {
        return [
            CustomerServiceLeadStatus::NOT_INTERESTED,
            CustomerServiceLeadStatus::REJECTED_COMMERCIAL_REGISTER,
            CustomerServiceLeadStatus::NO_CREDIT_SALES,
            CustomerServiceLeadStatus::NO_REGISTER_NO_ID_CONSENT,
            CustomerServiceLeadStatus::CLOSED,
        ];
    }

    public static function pipelineStageLabels(): array
    {
        return [
            self::PIPELINE_NOT_APPROACHED => 'لم يتم التواصل بعد',
            self::PIPELINE_WAITING_DOCUMENTS => 'في انتظار استكمال الأوراق',
            self::PIPELINE_READY_TO_SUBMIT => 'جاهز للتقديم',
            self::PIPELINE_SUBMITTED_TO_LENDER => 'تم التقديم وبانتظار جهة التمويل',
            self::PIPELINE_PENDING_CUSTOMER_UPDATE => 'قيد التعديل مع العميل',
            self::PIPELINE_APPROVED_WAITING_INVOICE => 'مقبول وفي انتظار إصدار فاتورة',
            self::PIPELINE_INVOICE_ISSUED => 'تم إصدار فاتورة',
            self::PIPELINE_SIGNED_CONTRACTS_PENDING => 'بانتظار توقيع العقود',
            self::PIPELINE_COLLECTION_IN_PROGRESS => 'التحصيل قيد المتابعة',
            self::PIPELINE_COLLECTION_COMPLETED => 'تم السداد بالكامل',
            self::PIPELINE_REJECTED_BY_LENDER => 'مرفوض من جهة التمويل',
        ];
    }

    public function list(PaginateRequest $request)
    {
        $actor = Auth::user();
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
        $term = trim((string)$request->get('term', ''));
        $tab = (string)$request->get('tab', self::TAB_ALL);
        $status = $request->get('status');

        $query = $this->baseLeadQuery($actor);

        if ($status) {
            $query->where('status', $status);
        } else {
            $this->applyTabFilter($query, $tab);
        }

        if ($term !== '') {
            $normalizedTerm = $this->normalizePhone($term);
            $query->where(function (Builder $filterQuery) use ($term, $normalizedTerm) {
                $filterQuery->whereHas('user', function (Builder $userQuery) use ($term, $normalizedTerm) {
                    $userQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('address', 'like', '%' . $term . '%')
                        ->orWhere('city', 'like', '%' . $term . '%')
                        ->orWhere('area', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $normalizedTerm . '%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')), '+', '') LIKE ?", ['%' . preg_replace('/[^0-9]/', '', $normalizedTerm) . '%']);
                })
                    ->orWhere('prospect_full_name', 'like', '%' . $term . '%')
                    ->orWhere('prospect_national_id_number', 'like', '%' . $normalizedTerm . '%');
            });
        }

        $result = $query
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->orderBy('priority_order')
            ->latest('updated_at')
            ->$method($methodValue);

        $decorate = function ($lead) {
            $lead->status_label = self::statusLabels()[$lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED] ?? $lead->status;
            return $lead;
        };

        if ($result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $result->setCollection($result->getCollection()->map($decorate));
            return $result;
        }

        return $result->map($decorate);
    }

    public function show(CustomerServiceLead $lead): CustomerServiceLead
    {
        $actor = Auth::user();
        $lead = $this->guardLeadAccess($lead, $actor);
        $lead->load([
            'user.creditApplications.facilities.repayments',
            'user.creditApplications.facilities.institution.financialInstitutionProfile',
            'user.creditApplications.facilities.employee',
            'user.creditApplications.submittedByCustomerService',
            'user.orders',
            'user.latestAddress',
            'assignedAgent',
            'activities.actor',
        ]);
        $lead->status_label = self::statusLabels()[$lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED] ?? $lead->status;
        $pipeline = $this->pipelineSnapshot($lead);
        $lead->last_pipeline_stage = $pipeline['stage'];
        $lead->last_pipeline_stage_at = $pipeline['stage_at'] ?? $lead->last_pipeline_stage_at;

        return $lead;
    }

    public function updateStatus(CustomerServiceLead $lead, CustomerServiceLeadStatusRequest $request): CustomerServiceLead
    {
        try {
            $actor = Auth::user();
            $lead = $this->guardLeadAccess($lead, $actor);

            DB::transaction(function () use ($lead, $request, $actor) {
                $lead->status = $request->status;
                $lead->priority_order = $this->priorityForStatus($request->status);
                $lead->latest_note = $request->note;
                $lead->last_contacted_at = now();
                $lead->next_follow_up_at = $request->filled('next_follow_up_at') ? $request->date('next_follow_up_at') : null;
                if ((int)$lead->assigned_to_user_id === 0 && $actor->hasRole(EnumRole::CUSTOMER_SERVICE)) {
                    $lead->assigned_to_user_id = $actor->id;
                    $lead->assigned_at = now();
                }
                $lead->save();

                $this->createActivity($lead, $actor, $request->status, $request->note, $lead->next_follow_up_at);
                $this->syncLeadPipelineColumns($lead);
            });

            return $this->show($lead);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function submitApplication(CustomerServiceLead $lead, CustomerServiceLeadApplicationRequest $request): CustomerServiceLead
    {
        try {
            $actor = Auth::user();
            $lead = $this->guardLeadAccess($lead, $actor);
            $lead->loadMissing('user.latestAddress');

            DB::transaction(function () use ($lead, $request, $actor) {
                $latitude = trim((string)$lead->user?->display_latitude);
                $longitude = trim((string)$lead->user?->display_longitude);

                if ($latitude === '' || $longitude === '') {
                    throw new Exception('لا يمكن تقديم طلب التمويل لهذا العميل قبل تسجيل خط العرض وخط الطول في بياناته.', 422);
                }

                $application = CreditApplication::where('user_id', $lead->user_id)
                    ->whereIn('status', [
                        CreditApplicationStatus::PENDING,
                        CreditApplicationStatus::PENDING_APPROVAL,
                    ])
                    ->latest()
                    ->first();

                if (!$application) {
                    $application = CreditApplication::create([
                        'user_id' => $lead->user_id,
                        'submitted_by_customer_service_user_id' => $actor->id,
                        'submitted_by_customer_service_at' => now(),
                        'full_name' => $request->full_name,
                        'national_id_number' => $request->national_id_number,
                        'status' => CreditApplicationStatus::PENDING,
                        'notes' => $request->note,
                    ]);
                } else {
                    if ((int)($application->submitted_by_customer_service_user_id ?? 0) === 0) {
                        $application->submitted_by_customer_service_user_id = $actor->id;
                        $application->submitted_by_customer_service_at = now();
                    }
                    $application->full_name = $request->full_name;
                    $application->national_id_number = $request->national_id_number;
                    $application->status = CreditApplicationStatus::PENDING;
                    $application->notes = $request->note;
                    $application->save();
                }

                $this->syncApplicationMedia(
                    $application,
                    $request->file('national_id_front_document'),
                    $request->file('national_id_back_document'),
                    $request->file('commercial_register_documents', []),
                    $request->file('tax_card_documents', []),
                    $request->file('rent_contract_documents', []),
                    $request->file('utility_bill_documents', []),
                    $request->file('additional_documents', [])
                );

                $lead->prospect_full_name = $request->full_name;
                $lead->prospect_national_id_number = $request->national_id_number;
                $lead->documents_status = CustomerServiceLeadStatus::DOCUMENTS_RECEIVED;
                $lead->status = CustomerServiceLeadStatus::DOCUMENTS_RECEIVED;
                $lead->priority_order = $this->priorityForStatus(CustomerServiceLeadStatus::DOCUMENTS_RECEIVED);
                $lead->latest_note = $request->note;
                $lead->last_contacted_at = now();
                if ((int)$lead->assigned_to_user_id === 0 && $actor->hasRole(EnumRole::CUSTOMER_SERVICE)) {
                    $lead->assigned_to_user_id = $actor->id;
                    $lead->assigned_at = now();
                }
                $lead->save();
                $this->syncLeadPipelineColumns($lead);

                $this->createActivity(
                    $lead,
                    $actor,
                    CustomerServiceLeadStatus::DOCUMENTS_RECEIVED,
                    $request->note ?: 'تم رفع بيانات العميل وصورة البطاقة وإنشاء طلب اشتري بالآجل من خدمة العملاء.',
                    null,
                    ['credit_application_id' => $application->id, 'submitted_via_customer_service' => true]
                );

                User::role(EnumRole::FINANCIAL_INSTITUTION)->get()->each(function (User $institutionUser) use ($application) {
                    $this->safeNotify($institutionUser, new NewCreditApplicationSubmittedNotification($application));
                });
            });

            return $this->show($lead);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function updateProfile(CustomerServiceLead $lead, \App\Http\Requests\CustomerServiceLeadProfileRequest $request): CustomerServiceLead
    {
        try {
            $actor = Auth::user();
            $lead = $this->guardLeadAccess($lead, $actor);
            $lead->loadMissing('user.latestAddress');

            DB::transaction(function () use ($lead, $request, $actor) {
                $user = $lead->user;
                if (!$user) {
                    throw new Exception('تعذر العثور على بيانات العميل.', 422);
                }

                $user->name = $request->filled('name') ? $request->name : $user->name;
                $user->address = $request->filled('address') ? $request->address : $user->address;
                $user->city = $request->filled('city') ? $request->city : $user->city;
                $user->area = $request->filled('area') ? $request->area : $user->area;
                $user->distribution_route = $request->filled('distribution_route') ? $request->distribution_route : $user->distribution_route;
                $user->latitude = $request->filled('latitude') ? $request->latitude : $user->latitude;
                $user->longitude = $request->filled('longitude') ? $request->longitude : $user->longitude;
                $user->estimated_average_monthly_purchase = $request->filled('estimated_average_monthly_purchase')
                    ? (float) $request->estimated_average_monthly_purchase
                    : $user->estimated_average_monthly_purchase;
                $user->save();

                $meta = $lead->meta ?: [];
                $meta['profile_updated_by_customer_service'] = true;
                $lead->meta = $meta;
                $lead->save();

                $this->createActivity(
                    $lead,
                    $actor,
                    $lead->status,
                    'تم تحديث بيانات العميل والموقع من شاشة خدمة العملاء.',
                    $lead->next_follow_up_at,
                    ['profile_updated' => true]
                );

                $this->syncLeadPipelineColumns($lead);
            });

            return $this->show($lead);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function dashboardSummary(): array
    {
        $actor = Auth::user();

        if ($actor->hasRole(EnumRole::CUSTOMER_SERVICE)) {
            $base = $this->baseLeadQuery($actor);
            $all = (clone $base)->get();
            $stageBreakdown = $this->pipelineBreakdown($all);

            return [
                'mode' => 'agent',
                'assigned_total' => $all->count(),
                'callbacks_count' => $all->whereIn('status', self::callbackStatuses())->count(),
                'waiting_documents_count' => $all->whereIn('status', self::waitingStatuses())->count(),
                'refused_count' => $all->whereIn('status', self::refusedStatuses())->count(),
                'not_approached_count' => $all->where('status', CustomerServiceLeadStatus::NOT_APPROACHED)->count() + $all->whereNull('status')->count(),
                'today_updates_count' => $actor->customerServiceLeadActivities()->whereDate('created_at', today())->count(),
                'week_updates_count' => $actor->customerServiceLeadActivities()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'status_breakdown' => $this->statusBreakdown($all),
                'pipeline_breakdown' => $stageBreakdown,
                'applications_submitted_count' => $stageBreakdown[self::PIPELINE_SUBMITTED_TO_LENDER]['count'] ?? 0,
                'pending_customer_update_count' => $stageBreakdown[self::PIPELINE_PENDING_CUSTOMER_UPDATE]['count'] ?? 0,
                'approved_count' => ($stageBreakdown[self::PIPELINE_APPROVED_WAITING_INVOICE]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_INVOICE_ISSUED]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_SIGNED_CONTRACTS_PENDING]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_COLLECTION_IN_PROGRESS]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_COLLECTION_COMPLETED]['count'] ?? 0),
                'invoice_issued_count' => $stageBreakdown[self::PIPELINE_INVOICE_ISSUED]['count'] ?? 0,
                'signed_contracts_count' => $stageBreakdown[self::PIPELINE_SIGNED_CONTRACTS_PENDING]['count'] ?? 0,
                'collections_in_progress_count' => $stageBreakdown[self::PIPELINE_COLLECTION_IN_PROGRESS]['count'] ?? 0,
                'collections_completed_count' => $stageBreakdown[self::PIPELINE_COLLECTION_COMPLETED]['count'] ?? 0,
                'rejected_by_lender_count' => $stageBreakdown[self::PIPELINE_REJECTED_BY_LENDER]['count'] ?? 0,
                'upcoming_callbacks' => $all->filter(fn (CustomerServiceLead $lead) => $lead->next_follow_up_at !== null)
                    ->sortBy('next_follow_up_at')
                    ->take(8)
                    ->values()
                    ->map(fn (CustomerServiceLead $lead) => $this->dashboardLeadPayload($lead)),
            ];
        }

        $base = CustomerServiceLead::query()
            ->whereHas('user', function (Builder $userQuery) {
                $userQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('id', EnumRole::CUSTOMER))
                    ->whereNull('deleted_at');
            });

        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->count();
        $totalOpenLeads = (clone $base)->count();
        $unassignedCount = (clone $base)->whereNull('assigned_to_user_id')->count();
        $callbacksCount = (clone $base)->whereIn('status', self::callbackStatuses())->count();
        $waitingDocumentsCount = (clone $base)->whereIn('status', self::waitingStatuses())->count();
        $refusedCount = (clone $base)->whereIn('status', self::refusedStatuses())->count();
        $notApproachedCount = (clone $base)->where(function (Builder $query) {
            $query->where('status', CustomerServiceLeadStatus::NOT_APPROACHED)
                ->orWhereNull('status');
        })->count();
        $statusBreakdown = $this->statusBreakdownFromQuery($base);
        $stageBreakdown = $this->pipelineBreakdownFromQuery($base);

        return [
            'mode' => 'manager',
            'total_open_leads' => $totalOpenLeads,
            'active_agents_count' => $agents,
            'unassigned_count' => $unassignedCount,
            'callbacks_count' => $callbacksCount,
            'waiting_documents_count' => $waitingDocumentsCount,
            'refused_count' => $refusedCount,
            'not_approached_count' => $notApproachedCount,
            'today_updates_count' => CustomerServiceLeadActivity::whereDate('created_at', today())->count(),
            'week_updates_count' => CustomerServiceLeadActivity::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'applications_submitted_count' => CreditApplication::whereNotNull('submitted_by_customer_service_user_id')->count(),
            'status_breakdown' => $statusBreakdown,
            'pipeline_breakdown' => $stageBreakdown,
            'approved_count' => ($stageBreakdown[self::PIPELINE_APPROVED_WAITING_INVOICE]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_INVOICE_ISSUED]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_SIGNED_CONTRACTS_PENDING]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_COLLECTION_IN_PROGRESS]['count'] ?? 0) + ($stageBreakdown[self::PIPELINE_COLLECTION_COMPLETED]['count'] ?? 0),
            'pending_customer_update_count' => $stageBreakdown[self::PIPELINE_PENDING_CUSTOMER_UPDATE]['count'] ?? 0,
            'invoice_issued_count' => $stageBreakdown[self::PIPELINE_INVOICE_ISSUED]['count'] ?? 0,
            'signed_contracts_count' => $stageBreakdown[self::PIPELINE_SIGNED_CONTRACTS_PENDING]['count'] ?? 0,
            'collections_in_progress_count' => $stageBreakdown[self::PIPELINE_COLLECTION_IN_PROGRESS]['count'] ?? 0,
            'collections_completed_count' => $stageBreakdown[self::PIPELINE_COLLECTION_COMPLETED]['count'] ?? 0,
            'rejected_by_lender_count' => $stageBreakdown[self::PIPELINE_REJECTED_BY_LENDER]['count'] ?? 0,
            'top_agents' => $this->agentDashboardCollection(),
        ];
    }

    public function reportSummary(Request $request): array
    {
        $actor = Auth::user();
        if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        [$dateFrom, $dateTo] = $this->resolveReportPeriod($request);

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'agents' => $this->agentPerformanceCollection($request),
        ];
    }

    public function redistribute(?int $perAgent = null): array
    {
        $actor = Auth::user();
        if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->get();
        if ($agents->isEmpty()) {
            throw new Exception('لا يوجد موظفو خدمة عملاء نشطون لإعادة التوزيع.', 422);
        }

        return DB::transaction(function () use ($agents, $perAgent) {
            $distribution = $this->reassignRedistributableLeadsAcrossActiveAgents($perAgent);

            return [
                'cycle' => $distribution['cycle'] ?? max((int) CustomerServiceLead::max('assignment_cycle'), 1),
                'agents_count' => $agents->count(),
                'per_agent' => $distribution['per_agent'] ?? $perAgent,
                'assigned_count' => $distribution['assigned_count'] ?? 0,
                'remaining_unassigned_count' => $distribution['remaining_unassigned_count'] ?? 0,
            ];
        });
    }

    public function assignFreshLeadsToAgent(User $agent, ?int $limit = null): array
    {
        if (!$agent->hasRole(EnumRole::CUSTOMER_SERVICE) || (int)$agent->status !== 5) {
            return [
                'agent_id' => $agent->id,
                'assigned_count' => 0,
                'current_assigned_count' => 0,
                'remaining_capacity' => 0,
            ];
        }

        return DB::transaction(function () use ($agent, $limit) {
            $currentAssignedCount = CustomerServiceLead::query()
                ->where('assigned_to_user_id', $agent->id)
                ->count();

            if (!is_null($limit) && $limit <= $currentAssignedCount) {
                return [
                    'agent_id' => $agent->id,
                    'assigned_count' => 0,
                    'current_assigned_count' => $currentAssignedCount,
                    'remaining_capacity' => 0,
                ];
            }

            $assignmentCycle = max((int)CustomerServiceLead::max('assignment_cycle'), 1);

            $query = $this->preferredUnassignedLeadQuery();
            if (!is_null($limit)) {
                $query->limit(max($limit - $currentAssignedCount, 0));
            }
            $leadIds = $query->pluck('id');

            if ($leadIds->isNotEmpty()) {
                CustomerServiceLead::whereIn('id', $leadIds)->update([
                    'assigned_to_user_id' => $agent->id,
                    'assigned_at' => now(),
                    'assignment_cycle' => $assignmentCycle,
                ]);
            }

            return [
                'agent_id' => $agent->id,
                'assigned_count' => $leadIds->count(),
                'current_assigned_count' => $currentAssignedCount + $leadIds->count(),
                'remaining_capacity' => is_null($limit) ? 0 : max($limit - ($currentAssignedCount + $leadIds->count()), 0),
            ];
        });
    }

    public function releaseAgentLeads(User $agent): int
    {
        return CustomerServiceLead::query()
            ->where('assigned_to_user_id', $agent->id)
            ->update([
                'assigned_to_user_id' => null,
                'assigned_at' => null,
            ]);
    }

    public function importCairoUsersWorkbook(string $path, bool $assign = true, ?int $perAgent = null): array
    {
        if (!file_exists($path)) {
            throw new Exception("Workbook not found: {$path}", 422);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            throw new Exception('ملف Cairo-users.xlsx لا يحتوي على بيانات.', 422);
        }

        $header = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $sourceFileName = basename($path);
        $sourceSheetName = pathinfo($sourceFileName, PATHINFO_FILENAME);
        $dataRows = collect(array_slice($rows, 1))
            ->map(function ($row) use ($header) {
                $assoc = [];
                foreach ($header as $index => $key) {
                    $assoc[$key !== '' ? $key : 'Unnamed: ' . $index] = $row[$index] ?? null;
                }
                return $assoc;
            })
            ->filter(function (array $row) {
                return !blank($this->extractWorkbookValue($row, [
                    'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
                ]));
            })
            ->values();

        $phoneCounts = $dataRows
            ->map(function (array $row) {
                return $this->normalizePhone($this->extractWorkbookValue($row, [
                    'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
                ]));
            })
            ->filter()
            ->countBy();

        $duplicatePhoneRowsSkipped = $dataRows->filter(function (array $row) use ($phoneCounts) {
            $phone = $this->normalizePhone($this->extractWorkbookValue($row, [
                'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
            ]));

            return $phone !== '' && (($phoneCounts[$phone] ?? 0) > 1);
        })->count();

        $duplicatePhoneUniqueCount = $phoneCounts->filter(fn (int $count) => $count > 1)->count();

        $cleanRows = $dataRows->filter(function (array $row) use ($phoneCounts) {
            $phone = $this->normalizePhone($this->extractWorkbookValue($row, [
                'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
            ]));

            return $phone !== '' && (($phoneCounts[$phone] ?? 0) === 1);
        })->values();

        $stats = DB::transaction(function () use ($cleanRows, $sourceFileName, $sourceSheetName) {
            $createdUsers = 0;
            $skippedExistingUsers = 0;
            $createdLeads = 0;
            $skippedRows = 0;

            foreach ($cleanRows as $row) {
                $phone = $this->normalizePhone($this->extractWorkbookValue($row, [
                    'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
                ]));

                if ($phone === '') {
                    $skippedRows++;
                    continue;
                }

                $name = $this->extractWorkbookValue($row, ['name', 'user name', 'customer name', 'العميل', 'اسم العميل', 'الاسم', 'اسم التاجر', 'اسم النشاط']) ?: ('عميل القاهرة ' . $phone);
                $address = $this->extractWorkbookValue($row, ['address', 'العنوان', 'عنوان', 'location address', 'branch address']);
                $city = $this->extractWorkbookValue($row, ['city', 'المدينة', 'المحافظة']);
                $area = $this->extractWorkbookValue($row, ['area', 'المنطقة', 'district']);
                $distributionRoute = $this->extractWorkbookValue($row, ['distribution route', 'route', 'route name', 'خط التوزيع']);
                $latitude = $this->extractWorkbookValue($row, ['latitude', 'lat', 'خط العرض']);
                $longitude = $this->extractWorkbookValue($row, ['longitude', 'long', 'lng', 'خط الطول']);
                $classification = $this->extractWorkbookValue($row, ['classification', 'class', 'التصنيف']);
                $businessType = $this->extractWorkbookValue($row, ['business type', 'activity type', 'نوع النشاط']);
                $estimatedAverageMonthlyPurchase = $this->extractWorkbookNumericValue($row, [
                    '12 month average purchase', 'average monthly purchase', 'average_monthly_purchase', 'متوسط الشراء الشهري', 'متوسط الشراء الشهري من ستاركوم في آخر ١٢ شهر',
                ]);

                $user = User::withTrashed()
                    ->with('roles')
                    ->where(function (Builder $query) use ($phone) {
                        $query->where('phone', $phone)
                            ->orWhere('username', $phone);
                    })
                    ->first();

                if ($user) {
                    $skippedExistingUsers++;
                    continue;
                }

                $user = new User();
                $user->name = $name;
                $user->email = null;
                $user->phone = $phone;
                $user->username = $phone;
                $user->password = Hash::make('123456');
                $user->status = 5;
                $user->country_code = '+20';
                $user->is_guest = 10;
                $user->email_verified_at = now();
                $user->address = $address;
                $user->city = $city;
                $user->area = $area;
                $user->distribution_route = $distributionRoute;
                $user->latitude = $latitude;
                $user->longitude = $longitude;
                if (!is_null($estimatedAverageMonthlyPurchase)) {
                    $user->estimated_average_monthly_purchase = $estimatedAverageMonthlyPurchase;
                }
                $user->save();
                $createdUsers++;

                if (!$user->hasRole(EnumRole::CUSTOMER)) {
                    $user->assignRole(EnumRole::CUSTOMER);
                }

                $lead = CustomerServiceLead::firstOrNew(['user_id' => $user->id]);
                $isNewLead = !$lead->exists;
                if ($isNewLead) {
                    $createdLeads++;
                    $lead->status = CustomerServiceLeadStatus::NOT_APPROACHED;
                    $lead->priority_order = $this->priorityForStatus(CustomerServiceLeadStatus::NOT_APPROACHED);
                }

                $meta = $lead->meta ?: [];
                $meta['import_sheets'] = array_values(array_unique(array_filter(array_merge($meta['import_sheets'] ?? [], [$sourceSheetName]))));
                $meta['import_source_file'] = $sourceFileName;
                $meta['classification'] = $classification ?: ($meta['classification'] ?? null);
                $meta['business_type'] = $businessType ?: ($meta['business_type'] ?? null);
                $meta['imported_latitude'] = $latitude ?: ($meta['imported_latitude'] ?? null);
                $meta['imported_longitude'] = $longitude ?: ($meta['imported_longitude'] ?? null);
                $lead->source_sheet = $sourceSheetName;
                $lead->imported_at = now();
                $lead->meta = $meta;
                $lead->save();
                $this->syncLeadPipelineColumns($lead);
            }

            return [
                'rows_total' => $cleanRows->count(),
                'created_users' => $createdUsers,
                'skipped_existing_users' => $skippedExistingUsers,
                'created_leads' => $createdLeads,
                'skipped_rows' => $skippedRows,
            ];
        });

        $assignment = null;
        if ($assign) {
            $assignment = $this->assignFreshLeadsAcrossActiveAgents();
        }

        return $stats + [
            'assignment' => $assignment,
            'original_rows_total' => $dataRows->count(),
            'duplicate_phone_rows_skipped' => $duplicatePhoneRowsSkipped,
            'duplicate_phone_unique_count' => $duplicatePhoneUniqueCount,
        ];
    }

    public function deleteUsersFromWorkbookByPhone(string $path, bool $dryRun = true): array
    {
        if (!file_exists($path)) {
            throw new Exception("Workbook not found: {$path}", 422);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            throw new Exception('الملف لا يحتوي على بيانات.', 422);
        }

        $header = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $phones = collect(array_slice($rows, 1))
            ->map(function ($row) use ($header) {
                $assoc = [];
                foreach ($header as $index => $key) {
                    $assoc[$key !== '' ? $key : 'Unnamed: ' . $index] = $row[$index] ?? null;
                }

                return $this->normalizePhone($this->extractWorkbookValue($assoc, [
                    'phone', 'phone number', 'mobile', 'رقم الهاتف', 'الهاتف', 'رقم الموبايل', 'رقم التليفون', 'تليفون',
                ]));
            })
            ->filter(fn (?string $phone) => !blank($phone))
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            throw new Exception('لم يتم العثور على أي أرقام هاتف صالحة داخل الملف.', 422);
        }

        $users = User::withTrashed()
            ->with([
                'customerServiceLead',
                'creditApplications.media',
            ])
            ->withCount([
                'creditApplications',
                'orders',
            ])
            ->where(function (Builder $query) use ($phones) {
                $query->whereIn('phone', $phones->all())
                    ->orWhereIn('username', $phones->all());
            })
            ->get();

        $stats = [
            'phones_in_file' => $phones->count(),
            'matched_users' => $users->count(),
            'deleted_users' => 0,
            'skipped_missing_users' => $phones->count() - $users->count(),
            'skipped_protected_users' => 0,
            'already_deleted_users' => 0,
            'deleted_leads' => 0,
            'deleted_activities' => 0,
        ];

        $protectedRows = [];
        $deletedRows = [];
        $matchedPhones = $users
            ->map(function (User $user) {
                return $this->normalizePhone($user->phone ?: $user->username ?: '');
            })
            ->filter()
            ->unique();

        $missingPhones = $phones
            ->diff($matchedPhones)
            ->values()
            ->all();

        $operation = function () use ($users, $dryRun, &$stats, &$protectedRows, &$deletedRows) {
            foreach ($users as $user) {
                $protectionReasons = $this->userNationalIdProtectionReasons($user);

                if (!empty($protectionReasons)) {
                    $stats['skipped_protected_users']++;
                    $protectedRows[] = [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'phone' => trim(($user->country_code ?: '') . ' ' . ($user->phone ?: '')),
                        'reason' => implode(' | ', $protectionReasons),
                    ];
                    continue;
                }

                if ($user->trashed()) {
                    $stats['already_deleted_users']++;
                    $deletedRows[] = [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'phone' => trim(($user->country_code ?: '') . ' ' . ($user->phone ?: '')),
                        'status' => 'already_deleted',
                    ];
                    continue;
                }

                $lead = $user->customerServiceLead;
                if ($lead) {
                    $activitiesCount = $lead->activities()->count();
                    if (!$dryRun) {
                        $lead->activities()->delete();
                        $lead->delete();
                    }
                    $stats['deleted_activities'] += $activitiesCount;
                    $stats['deleted_leads']++;
                }

                if (!$dryRun) {
                    $user->delete();
                }

                $stats['deleted_users']++;
                $deletedRows[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => trim(($user->country_code ?: '') . ' ' . ($user->phone ?: '')),
                    'status' => $dryRun ? 'dry_run' : 'deleted',
                ];
            }
        };

        if ($dryRun) {
            $operation();
        } else {
            DB::transaction($operation);
        }

        return $stats + [
            'dry_run' => $dryRun,
            'deleted_rows' => $deletedRows,
            'protected_rows' => $protectedRows,
            'missing_phones' => $missingPhones,
        ];
    }

    public function importWorkbook(string $path): array
    {
        if (!file_exists($path)) {
            throw new Exception("Workbook not found: {$path}", 422);
        }

        $spreadsheet = IOFactory::load($path);
        $now = now();

        $statusMap = self::statusLabels();
        $reverseStatusMap = [];
        foreach ($statusMap as $code => $label) {
            $reverseStatusMap[$label] = $code;
        }

        $customers = User::with('roles')
            ->whereHas('roles', fn (Builder $query) => $query->where('id', EnumRole::CUSTOMER))
            ->get();

        $usersByPhone = $customers->keyBy(function (User $user) {
            return $this->normalizePhone($user->phone ?: '');
        });

        $createdLeads = 0;
        foreach ($customers as $customer) {
            CustomerServiceLead::firstOrCreate(
                ['user_id' => $customer->id],
                [
                    'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
                    'priority_order' => $this->priorityForStatus(CustomerServiceLeadStatus::NOT_APPROACHED),
                ]
            );
        }

        $matchedRows = 0;
        $unmatchedRows = 0;
        $updatedSheet7Rows = 0;

        $mainRows = $this->sheetRows($spreadsheet, 'العملا');
        foreach ($mainRows as $row) {
            $user = $usersByPhone->get($this->normalizePhone($row['رقم الهاتف للمستخدم'] ?? ''));
            if (!$user) {
                $unmatchedRows++;
                continue;
            }

            $lead = CustomerServiceLead::firstOrNew(['user_id' => $user->id]);
            if (!$lead->exists) {
                $createdLeads++;
            }

            $statusCode = $reverseStatusMap[trim((string)($row['الحالة'] ?? ''))] ?? CustomerServiceLeadStatus::NOT_APPROACHED;

            $lead->status = $statusCode;
            $lead->priority_order = $this->priorityForStatus($statusCode);
            $lead->source_sheet = 'العملا';
            $lead->source_status = trim((string)($row['الحالة'] ?? ''));
            $lead->imported_at = $now;
            $lead->meta = array_merge($lead->meta ?: [], [
                'latitude' => $row['Latitude'] ?? null,
                'longitude' => $row['Longitude'] ?? null,
                'distribution_route' => $row['خط التوزيع'] ?? null,
                'import_sheets' => array_values(array_unique(array_filter(array_merge($lead->meta['import_sheets'] ?? [], ['العملا'])))),
            ]);
            $lead->save();

            $this->fillUserContactFields($user, [
                'name' => $row['التاجر'] ?? null,
                'address' => $row['عنوان الفرع'] ?? null,
                'city' => $row['المدينة'] ?? null,
                'area' => $row['المنطقة'] ?? null,
                'latitude' => $row['Latitude'] ?? null,
                'longitude' => $row['Longitude'] ?? null,
                'distribution_route' => $row['خط التوزيع'] ?? null,
            ]);

            if (!$lead->activities()->exists() && $lead->source_status) {
                $this->createActivity(
                    $lead,
                    null,
                    $lead->status,
                    'تم استيراد حالة العميل من ملف عملا التمويل.',
                    null,
                    ['import_source' => 'العملا']
                );
            }

            $matchedRows++;
        }

        $receivedRows = $this->sheetRows($spreadsheet, 'تم الاستلام');
        foreach ($receivedRows as $row) {
            $user = $usersByPhone->get($this->normalizePhone($row['phone'] ?? ''));
            if (!$user) {
                continue;
            }

            $lead = CustomerServiceLead::firstOrCreate(['user_id' => $user->id], [
                'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
                'priority_order' => $this->priorityForStatus(CustomerServiceLeadStatus::NOT_APPROACHED),
            ]);

            $lead->status = CustomerServiceLeadStatus::DOCUMENTS_RECEIVED;
            $lead->documents_status = 'received_offline';
            $lead->priority_order = $this->priorityForStatus(CustomerServiceLeadStatus::DOCUMENTS_RECEIVED);
            $lead->imported_at = $now;
            $lead->meta = array_merge($lead->meta ?: [], [
                'import_sheets' => array_values(array_unique(array_filter(array_merge($lead->meta['import_sheets'] ?? [], ['تم الاستلام'])))),
            ]);
            $lead->save();
        }

        $identityRows = $this->sheetRows($spreadsheet, 'Sheet7');
        foreach ($identityRows as $row) {
            $user = $usersByPhone->get($this->normalizePhone($row['رقم الهاتف للمستخدم'] ?? ''));
            if (!$user) {
                continue;
            }

            $lead = CustomerServiceLead::firstOrCreate(['user_id' => $user->id], [
                'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
                'priority_order' => $this->priorityForStatus(CustomerServiceLeadStatus::NOT_APPROACHED),
            ]);

            $lead->prospect_full_name = $this->normalizeWorkbookValue($row['الاسم في الرقم القومي'] ?? null) ?: $lead->prospect_full_name;
            $lead->prospect_national_id_number = $this->normalizeArabicDigits((string)($row['رقم القومي'] ?? '')) ?: $lead->prospect_national_id_number;
            $lead->documents_status = 'uploaded_in_legacy_sheet';
            if (in_array($lead->status, [null, CustomerServiceLeadStatus::NOT_APPROACHED, CustomerServiceLeadStatus::WAITING_DOCUMENTS], true)) {
                $lead->status = CustomerServiceLeadStatus::DOCUMENTS_RECEIVED;
                $lead->priority_order = $this->priorityForStatus(CustomerServiceLeadStatus::DOCUMENTS_RECEIVED);
            }
            $lead->meta = array_merge($lead->meta ?: [], [
                'sheet7_status' => $row['الحالة'] ?? null,
                'sheet7_notes' => $row['Unnamed: 11'] ?? null,
                'import_sheets' => array_values(array_unique(array_filter(array_merge($lead->meta['import_sheets'] ?? [], ['Sheet7'])))),
            ]);
            $lead->save();

            $application = CreditApplication::where('user_id', $user->id)->latest()->first();
            if ($application) {
                $application->full_name = $application->full_name ?: $lead->prospect_full_name;
                $application->national_id_number = $application->national_id_number ?: $lead->prospect_national_id_number;
                $application->save();
            }

            $updatedSheet7Rows++;
        }

        foreach (['جملة مخابز حلويات', 'فواتير فوق ٨ الاف', 'آجل كرتونة', 'Sheet2'] as $sheetName) {
            foreach ($this->sheetRows($spreadsheet, $sheetName) as $row) {
                $user = $usersByPhone->get($this->normalizePhone($row['رقم الهاتف للمستخدم'] ?? ''));
                if (!$user) {
                    continue;
                }

                $lead = CustomerServiceLead::firstOrCreate(['user_id' => $user->id], [
                    'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
                    'priority_order' => $this->priorityForStatus(CustomerServiceLeadStatus::NOT_APPROACHED),
                ]);

                $meta = $lead->meta ?: [];
                $segments = $meta['segments'] ?? [];
                $segments[] = $sheetName;
                $meta['segments'] = array_values(array_unique(array_filter($segments)));
                $meta['import_sheets'] = array_values(array_unique(array_filter(array_merge($meta['import_sheets'] ?? [], [$sheetName]))));
                $lead->meta = $meta;
                $lead->save();
            }
        }

        return [
            'matched_rows' => $matchedRows,
            'unmatched_rows' => $unmatchedRows,
            'sheet7_rows_updated' => $updatedSheet7Rows,
            'leads_total' => CustomerServiceLead::count(),
        ];
    }

    protected function baseLeadQuery(User $actor, bool $allowAllForManagers = false): Builder
    {
        $query = CustomerServiceLead::with([
            'user.creditApplications.facilities.repayments',
            'user.creditApplications.facilities.institution.financialInstitutionProfile',
            'user.creditApplications.facilities.employee',
            'user.orders',
            'user.latestAddress',
            'assignedAgent',
        ])->whereHas('user', function (Builder $userQuery) {
            $userQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('id', EnumRole::CUSTOMER))
                ->whereNull('deleted_at');
        });

        if ($actor->hasRole(EnumRole::CUSTOMER_SERVICE)) {
            $query->where('assigned_to_user_id', $actor->id);
        } elseif (!$allowAllForManagers) {
            if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    protected function redistributableLeadQuery(): Builder
    {
        return CustomerServiceLead::query()
            ->whereHas('user', function (Builder $userQuery) {
                $userQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('id', EnumRole::CUSTOMER))
                    ->whereNull('deleted_at');
            })
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', CustomerServiceLeadStatus::NOT_APPROACHED)
                    ->orWhereIn('status', self::callbackStatuses())
                    ->orWhereIn('status', self::waitingStatuses())
                    ->orWhereIn('status', self::refusedStatuses());
            });
    }

    protected function unassignedLeadQuery(): Builder
    {
        return $this->redistributableLeadQuery()->whereNull('assigned_to_user_id');
    }

    protected function applyTabFilter(Builder $query, string $tab): void
    {
        if ($tab === self::TAB_CALLBACK) {
            $query->whereIn('status', self::callbackStatuses());
            return;
        }

        if ($tab === self::TAB_WAITING) {
            $query->whereIn('status', self::waitingStatuses());
            return;
        }

        if ($tab === self::TAB_REFUSED) {
            $query->whereIn('status', self::refusedStatuses());
            return;
        }
    }

    protected function guardLeadAccess(CustomerServiceLead $lead, User $actor): CustomerServiceLead
    {
        $lead->loadMissing('user.creditApplications', 'assignedAgent');

        if ($actor->hasRole(EnumRole::CUSTOMER_SERVICE) && (int)$lead->assigned_to_user_id !== (int)$actor->id) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        if (
            !$actor->hasRole(EnumRole::CUSTOMER_SERVICE) &&
            !$actor->hasRole(EnumRole::ADMIN) &&
            !$actor->hasRole(EnumRole::MANAGER)
        ) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        return $lead;
    }

    protected function createActivity(
        CustomerServiceLead $lead,
        ?User $actor,
        ?string $status,
        ?string $note,
        $nextFollowUpAt = null,
        array $meta = []
    ): CustomerServiceLeadActivity {
        return CustomerServiceLeadActivity::create([
            'customer_service_lead_id' => $lead->id,
            'actor_user_id' => $actor?->id,
            'status' => $status,
            'note' => $note,
            'next_follow_up_at' => $nextFollowUpAt,
            'meta' => $meta,
        ]);
    }

    protected function statusBreakdown(Collection $leads): array
    {
        $labels = self::statusLabels();
        $breakdown = [];

        foreach ($labels as $status => $label) {
            $breakdown[] = [
                'status' => $status,
                'label' => $label,
                'count' => $leads->where('status', $status)->count(),
            ];
        }

        $breakdown[] = [
            'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
            'label' => $labels[CustomerServiceLeadStatus::NOT_APPROACHED],
            'count' => $leads->whereNull('status')->count(),
        ];

        return $breakdown;
    }

    protected function statusBreakdownFromQuery(Builder $base): array
    {
        $labels = self::statusLabels();
        $counts = (clone $base)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $breakdown = [];
        foreach ($labels as $status => $label) {
            $breakdown[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ];
        }

        $breakdown[] = [
            'status' => CustomerServiceLeadStatus::NOT_APPROACHED,
            'label' => $labels[CustomerServiceLeadStatus::NOT_APPROACHED],
            'count' => (int) ($counts[null] ?? 0),
        ];

        return $breakdown;
    }

    protected function pipelineBreakdown(Collection $leads): array
    {
        $labels = self::pipelineStageLabels();
        $breakdown = [];

        foreach ($labels as $stage => $label) {
            $breakdown[$stage] = [
                'stage' => $stage,
                'label' => $label,
                'count' => 0,
            ];
        }

        foreach ($leads as $lead) {
            $snapshot = $this->pipelineSnapshot($lead);
            $stage = $snapshot['stage'] ?? self::PIPELINE_NOT_APPROACHED;
            if (!isset($breakdown[$stage])) {
                $breakdown[$stage] = [
                    'stage' => $stage,
                    'label' => $snapshot['stage_label'] ?? $stage,
                    'count' => 0,
                ];
            }
            $breakdown[$stage]['count']++;
        }

        return $breakdown;
    }

    protected function pipelineBreakdownFromQuery(Builder $base): array
    {
        $labels = self::pipelineStageLabels();
        $breakdown = [];

        foreach ($labels as $stage => $label) {
            $breakdown[$stage] = [
                'stage' => $stage,
                'label' => $label,
                'count' => 0,
            ];
        }

        $counts = (clone $base)
            ->select(DB::raw("COALESCE(last_pipeline_stage, '" . self::PIPELINE_NOT_APPROACHED . "') as stage"), DB::raw('COUNT(*) as aggregate'))
            ->groupBy('stage')
            ->get();

        foreach ($counts as $row) {
            $stage = (string) $row->stage;
            if (!isset($breakdown[$stage])) {
                $breakdown[$stage] = [
                    'stage' => $stage,
                    'label' => $labels[$stage] ?? $stage,
                    'count' => 0,
                ];
            }
            $breakdown[$stage]['count'] = (int) $row->aggregate;
        }

        return $breakdown;
    }

    protected function agentDashboardCollection(): array
    {
        $dateFrom = now()->startOfWeek();
        $dateTo = now()->endOfWeek();

        return User::role(EnumRole::CUSTOMER_SERVICE)
            ->where('status', 5)
            ->get()
            ->map(function (User $agent) use ($dateFrom, $dateTo) {
                return [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'assigned_leads_count' => CustomerServiceLead::where('assigned_to_user_id', $agent->id)->count(),
                    'period_updates_count' => $agent->customerServiceLeadActivities()->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                    'period_applications_submitted_count' => CreditApplication::where('submitted_by_customer_service_user_id', $agent->id)->whereBetween('submitted_by_customer_service_at', [$dateFrom, $dateTo])->count(),
                ];
            })
            ->sortByDesc(function (array $agent) {
                return ($agent['period_updates_count'] ?? 0) + ($agent['period_applications_submitted_count'] ?? 0);
            })
            ->take(8)
            ->values()
            ->all();
    }

    protected function pipelineSnapshot(CustomerServiceLead $lead): array
    {
        $lead->loadMissing([
            'user.creditApplications.facilities.repayments',
            'user.creditApplications.facilities.institution.financialInstitutionProfile',
            'user.creditApplications.facilities.employee',
            'user.creditApplications.submittedByCustomerService',
            'user.orders',
            'user.latestAddress',
        ]);

        $application = $lead->user?->creditApplications?->sortByDesc('id')->first();
        $facility = $application?->facilities?->sortByDesc('id')->first();
        $repayments = $facility?->repayments ?: collect();
        $orders = $lead->user?->orders ?: collect();
        $signedContractsCount = count($facility?->signed_contract_documents ?? []);
        $contractsCount = count($facility?->contract_documents ?? []);
        $invoiceCount = $facility
            ? $orders->filter(function ($order) use ($facility) {
                if (!$facility->starts_at || !$order->created_at) {
                    return true;
                }
                return $order->created_at->greaterThanOrEqualTo($facility->starts_at->copy()->startOfDay());
            })->count()
            : 0;
        $repaidAmount = (float) $repayments->sum('amount');
        $remainingDue = max(0, (float) ($facility?->approved_amount ?? 0) - $repaidAmount);

        $stage = self::PIPELINE_NOT_APPROACHED;
        $stageAt = $lead->updated_at;

        if (blank($application)) {
            if (($lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED) === CustomerServiceLeadStatus::DOCUMENTS_RECEIVED) {
                $stage = self::PIPELINE_READY_TO_SUBMIT;
            } elseif (in_array($lead->status, self::waitingStatuses(), true)) {
                $stage = self::PIPELINE_WAITING_DOCUMENTS;
            } else {
                $stage = self::PIPELINE_NOT_APPROACHED;
            }
        } elseif ($facility && in_array($facility->status, ['approved', 'settled', 'expired'], true)) {
            $stageAt = $facility->reviewed_at ?: $facility->updated_at ?: $facility->created_at;

            if ($remainingDue <= 0 && $repaidAmount > 0) {
                $stage = self::PIPELINE_COLLECTION_COMPLETED;
                $stageAt = $repayments->sortByDesc('paid_at')->first()?->paid_at ?: $stageAt;
            } elseif ($repaidAmount > 0) {
                $stage = self::PIPELINE_COLLECTION_IN_PROGRESS;
                $stageAt = $repayments->sortByDesc('paid_at')->first()?->paid_at ?: $stageAt;
            } elseif ($signedContractsCount > 0) {
                $stage = self::PIPELINE_SIGNED_CONTRACTS_PENDING;
            } elseif ($invoiceCount > 0 || $contractsCount > 0) {
                $stage = self::PIPELINE_INVOICE_ISSUED;
            } else {
                $stage = self::PIPELINE_APPROVED_WAITING_INVOICE;
            }
        } else {
            if ($application?->status === CreditApplicationStatus::DECLINED) {
                $stage = self::PIPELINE_REJECTED_BY_LENDER;
                $stageAt = $application->updated_at ?: $application->created_at;
            } elseif ($application?->status === CreditApplicationStatus::PENDING_APPROVAL) {
                $stage = self::PIPELINE_PENDING_CUSTOMER_UPDATE;
                $stageAt = $application->updated_at ?: $application->created_at;
            } else {
                $stage = self::PIPELINE_SUBMITTED_TO_LENDER;
                $stageAt = $application?->created_at ?: $lead->updated_at;
            }
        }

        return [
            'stage' => $stage,
            'stage_label' => self::pipelineStageLabels()[$stage] ?? $stage,
            'stage_at' => $stageAt,
            'application' => $application,
            'facility' => $facility,
            'invoice_count' => $invoiceCount,
            'contracts_count' => $contractsCount,
            'signed_contracts_count' => $signedContractsCount,
            'repayments_count' => $repayments->count(),
            'repaid_amount' => $repaidAmount,
            'remaining_due' => $remainingDue,
        ];
    }

    protected function syncLeadPipelineColumns(CustomerServiceLead $lead): void
    {
        $snapshot = $this->pipelineSnapshot($lead);
        $lead->last_pipeline_stage = $snapshot['stage'];
        $lead->last_pipeline_stage_at = $snapshot['stage_at'] ?? $lead->last_pipeline_stage_at;
        $lead->saveQuietly();
    }

    protected function agentPerformanceCollection(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->resolveReportPeriod($request);

        return User::role(EnumRole::CUSTOMER_SERVICE)
            ->where('status', 5)
            ->get()
            ->map(function (User $agent) use ($dateFrom, $dateTo) {
                $currentAssignedLeads = CustomerServiceLead::with([
                    'user.creditApplications.facilities.repayments',
                    'user.creditApplications.facilities.institution.financialInstitutionProfile',
                    'user.creditApplications.facilities.employee',
                    'user.orders',
                    'user.latestAddress',
                ])
                    ->where('assigned_to_user_id', $agent->id)
                    ->get();

                $activities = $agent->customerServiceLeadActivities()
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->orderBy('created_at')
                    ->get();

                $activityLeadIds = $activities->pluck('customer_service_lead_id')->filter()->unique()->values();

                $assignedLeadIds = CustomerServiceLead::query()
                    ->where('assigned_to_user_id', $agent->id)
                    ->whereBetween('assigned_at', [$dateFrom, $dateTo])
                    ->pluck('id');

                $applicationUserIds = CreditApplication::query()
                    ->where('submitted_by_customer_service_user_id', $agent->id)
                    ->whereBetween('submitted_by_customer_service_at', [$dateFrom, $dateTo])
                    ->pluck('user_id');

                $applicationLeadIds = CustomerServiceLead::query()
                    ->whereIn('user_id', $applicationUserIds)
                    ->pluck('id');

                $periodLeadIds = $activityLeadIds
                    ->merge($assignedLeadIds)
                    ->merge($applicationLeadIds)
                    ->unique()
                    ->values();

                $periodLeads = CustomerServiceLead::with('user')
                    ->whereIn('id', $periodLeadIds)
                    ->get();

                $latestStatusesByLead = $activities
                    ->groupBy('customer_service_lead_id')
                    ->map(function ($leadActivities) {
                        $latestActivity = $leadActivities->sortByDesc('created_at')->first();
                        return $latestActivity?->status;
                    });

                $leadStatusCollection = $periodLeads->map(function (CustomerServiceLead $lead) use ($latestStatusesByLead, $dateFrom, $dateTo) {
                    $effectiveStatus = $latestStatusesByLead->get($lead->id);

                    if (blank($effectiveStatus)) {
                        $effectiveStatus = $lead->assigned_at && $lead->assigned_at->between($dateFrom, $dateTo)
                            ? CustomerServiceLeadStatus::NOT_APPROACHED
                            : ($lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED);
                    }

                    return [
                        'lead_id' => $lead->id,
                        'status' => $effectiveStatus ?: CustomerServiceLeadStatus::NOT_APPROACHED,
                    ];
                });

                $periodApplicationsSubmittedCount = CreditApplication::query()
                    ->where('submitted_by_customer_service_user_id', $agent->id)
                    ->whereBetween('submitted_by_customer_service_at', [$dateFrom, $dateTo])
                    ->count();

                $periodUserIds = $periodLeads->pluck('user_id')->unique()->values();
                $approvedFacilitiesCount = CreditFacility::query()
                    ->whereIn('user_id', $periodUserIds)
                    ->whereIn('status', ['approved', 'settled', 'expired'])
                    ->whereBetween(DB::raw('COALESCE(reviewed_at, updated_at, created_at)'), [$dateFrom, $dateTo])
                    ->count();

                $declinedApplicationsCount = CreditApplication::query()
                    ->whereIn('user_id', $periodUserIds)
                    ->where('status', CreditApplicationStatus::DECLINED)
                    ->whereBetween('updated_at', [$dateFrom, $dateTo])
                    ->count();

                $invoiceIssuedCount = DB::table('orders')
                    ->whereIn('user_id', $periodUserIds)
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->count();

                $repaymentsRecordedCount = CreditFacilityRepayment::query()
                    ->whereIn('user_id', $periodUserIds)
                    ->whereBetween('paid_at', [$dateFrom, $dateTo])
                    ->count();

                $signedContractsCount = CreditFacility::query()
                    ->whereIn('user_id', $periodUserIds)
                    ->whereBetween('updated_at', [$dateFrom, $dateTo])
                    ->get()
                    ->filter(fn (CreditFacility $facility) => count($facility->signed_contract_documents) > 0)
                    ->count();

                $lastActivityAt = $activities->sortByDesc('created_at')->first()?->created_at;
                $distinctActiveDaysCount = $activities->pluck('created_at')
                    ->filter()
                    ->map(fn ($date) => Carbon::parse($date)->toDateString())
                    ->unique()
                    ->count();

                $assignedCustomers = $currentAssignedLeads
                    ->map(function (CustomerServiceLead $lead) {
                        $snapshot = $this->pipelineSnapshot($lead);

                        return [
                            'lead_id' => $lead->id,
                            'user_id' => $lead->user_id,
                            'customer_name' => $lead->user?->name,
                            'phone' => trim(($lead->user?->country_code ?: '') . ' ' . ($lead->user?->phone ?: '')),
                            'address' => $lead->user?->display_address,
                            'status_label' => self::statusLabels()[$lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED] ?? ($lead->status ?: '--'),
                            'pipeline_label' => $snapshot['stage_label'] ?? '--',
                            'average_purchase' => (float) ($lead->user?->estimated_average_monthly_purchase ?? 0),
                        ];
                    })
                    ->sortBy('customer_name')
                    ->values()
                    ->all();

                $submittedCustomers = CreditApplication::with('user')
                    ->where('submitted_by_customer_service_user_id', $agent->id)
                    ->whereBetween('submitted_by_customer_service_at', [$dateFrom, $dateTo])
                    ->get()
                    ->map(function (CreditApplication $application) {
                        return [
                            'application_id' => $application->id,
                            'customer_name' => $application->user?->name,
                            'full_name' => $application->full_name,
                            'national_id_number' => $application->national_id_number,
                            'submitted_at' => $application->submitted_by_customer_service_at?->toDateTimeString(),
                            'status' => $application->status,
                        ];
                    })
                    ->values()
                    ->all();

                $approvedCustomers = $currentAssignedLeads
                    ->map(function (CustomerServiceLead $lead) {
                        $snapshot = $this->pipelineSnapshot($lead);
                        $facility = $snapshot['facility'] ?? null;
                        if (!$facility || !in_array($facility->status, ['approved', 'settled', 'expired'], true)) {
                            return null;
                        }

                        return [
                            'lead_id' => $lead->id,
                            'customer_name' => $lead->user?->name,
                            'institution_name' => $facility->institution?->name,
                            'employee_name' => $facility->employee?->name,
                            'approved_amount' => (float) ($facility->approved_amount ?? 0),
                            'remaining_due' => (float) ($snapshot['remaining_due'] ?? 0),
                            'pipeline_label' => $snapshot['stage_label'] ?? '--',
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $collectionCustomers = $currentAssignedLeads
                    ->map(function (CustomerServiceLead $lead) {
                        $snapshot = $this->pipelineSnapshot($lead);
                        $facility = $snapshot['facility'] ?? null;
                        if (!$facility || (($snapshot['repaid_amount'] ?? 0) <= 0 && ($snapshot['remaining_due'] ?? 0) <= 0)) {
                            return null;
                        }

                        return [
                            'lead_id' => $lead->id,
                            'customer_name' => $lead->user?->name,
                            'approved_amount' => (float) ($facility->approved_amount ?? 0),
                            'repaid_amount' => (float) ($snapshot['repaid_amount'] ?? 0),
                            'remaining_due' => (float) ($snapshot['remaining_due'] ?? 0),
                            'repayments_count' => (int) ($snapshot['repayments_count'] ?? 0),
                            'pipeline_label' => $snapshot['stage_label'] ?? '--',
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'assigned_leads_count' => $periodLeads->count(),
                    'period_leads_count' => $periodLeads->count(),
                    'not_approached_count' => $leadStatusCollection->where('status', CustomerServiceLeadStatus::NOT_APPROACHED)->count(),
                    'waiting_documents_count' => $leadStatusCollection->whereIn('status', self::waitingStatuses())->count(),
                    'callbacks_count' => $leadStatusCollection->whereIn('status', self::callbackStatuses())->count(),
                    'refused_count' => $leadStatusCollection->whereIn('status', self::refusedStatuses())->count(),
                    'period_updates_count' => $activities->count(),
                    'period_applications_submitted_count' => $periodApplicationsSubmittedCount,
                    'approved_facilities_count' => $approvedFacilitiesCount,
                    'declined_applications_count' => $declinedApplicationsCount,
                    'invoices_issued_count' => $invoiceIssuedCount,
                    'signed_contracts_count' => $signedContractsCount,
                    'repayments_recorded_count' => $repaymentsRecordedCount,
                    'total_applications_submitted_count' => $periodApplicationsSubmittedCount,
                    'active_days_count' => $distinctActiveDaysCount,
                    'today_updates_count' => $distinctActiveDaysCount,
                    'week_updates_count' => $distinctActiveDaysCount,
                    'last_activity_at' => $lastActivityAt?->toDateTimeString(),
                    'details' => [
                        'assigned_customers' => $assignedCustomers,
                        'submitted_customers' => $submittedCustomers,
                        'approved_customers' => $approvedCustomers,
                        'collection_customers' => $collectionCustomers,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveReportPeriod(Request $request): array
    {
        $dateFromInput = $request->get('date_from') ?: $request->get('from_date');
        $dateToInput = $request->get('date_to') ?: $request->get('to_date');

        if ($dateFromInput && !$dateToInput) {
            $dateToInput = $dateFromInput;
        }

        if ($dateToInput && !$dateFromInput) {
            $dateFromInput = $dateToInput;
        }

        $dateFrom = $dateFromInput
            ? Carbon::parse($dateFromInput, config('app.timezone'))->startOfDay()
            : now(config('app.timezone'))->subDays(6)->startOfDay();

        $dateTo = $dateToInput
            ? Carbon::parse($dateToInput, config('app.timezone'))->endOfDay()
            : now(config('app.timezone'))->endOfDay();

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    protected function dashboardLeadPayload(CustomerServiceLead $lead): array
    {
        return [
            'id' => $lead->id,
            'user_name' => $lead->user?->name,
            'phone' => trim(($lead->user?->country_code ?: '') . ' ' . ($lead->user?->phone ?: '')),
            'status' => $lead->status,
            'status_label' => self::statusLabels()[$lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED] ?? $lead->status,
            'next_follow_up_at' => $lead->next_follow_up_at?->toDateTimeString(),
        ];
    }

    protected function priorityForStatus(?string $status): int
    {
        return match ($status) {
            null, CustomerServiceLeadStatus::NOT_APPROACHED => 1,
            CustomerServiceLeadStatus::WAITING_DOCUMENTS => 2,
            CustomerServiceLeadStatus::DOCUMENTS_RECEIVED => 3,
            CustomerServiceLeadStatus::NO_ANSWER,
            CustomerServiceLeadStatus::CONTACTED_WAITING_REPLY,
            CustomerServiceLeadStatus::CALL_LATER,
            CustomerServiceLeadStatus::REVIEW_WITH_OWNER => 4,
            CustomerServiceLeadStatus::VISIT_REQUIRED => 5,
            CustomerServiceLeadStatus::NOT_INTERESTED,
            CustomerServiceLeadStatus::REJECTED_COMMERCIAL_REGISTER,
            CustomerServiceLeadStatus::NO_CREDIT_SALES,
            CustomerServiceLeadStatus::NO_REGISTER_NO_ID_CONSENT,
            CustomerServiceLeadStatus::CLOSED => 6,
            default => 7,
        };
    }

    protected function preferredUnassignedLeadQuery(): Builder
    {
        return $this->unassignedLeadQuery()
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', CustomerServiceLeadStatus::NOT_APPROACHED)
                    ->orWhere('status', CustomerServiceLeadStatus::NO_ANSWER)
                    ->orWhere('status', CustomerServiceLeadStatus::CLOSED);
            })
            ->orderByRaw('CASE WHEN last_contacted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('priority_order')
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->latest('updated_at');
    }

    protected function redistributionEligibleLeadQuery(): Builder
    {
        return $this->redistributableLeadQuery()
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', CustomerServiceLeadStatus::NOT_APPROACHED)
                    ->orWhereIn('status', self::callbackStatuses())
                    ->orWhere('status', CustomerServiceLeadStatus::CLOSED);
            })
            ->orderByRaw('CASE WHEN last_contacted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('priority_order')
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->latest('updated_at');
    }

    protected function assignFreshLeadsAcrossActiveAgents(?int $perAgent = null): array
    {
        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->get();
        if ($agents->isEmpty()) {
            return [
                'agents_count' => 0,
                'assigned_count' => 0,
                'remaining_unassigned_count' => $this->preferredUnassignedLeadQuery()->count(),
                'per_agent' => $perAgent,
            ];
        }

        $pool = $this->preferredUnassignedLeadQuery()->get()->values();
        if ($pool->isEmpty()) {
            return [
                'agents_count' => $agents->count(),
                'assigned_count' => 0,
                'remaining_unassigned_count' => 0,
                'per_agent' => $perAgent,
            ];
        }

        $assignmentCycle = max((int) CustomerServiceLead::max('assignment_cycle'), 1);
        $chunks = array_fill(0, $agents->count(), []);
        foreach ($pool->values() as $index => $lead) {
            $chunks[$index % $agents->count()][] = $lead->id;
        }

        $assignedCount = 0;
        foreach ($agents->values() as $index => $agent) {
            $leadIds = $chunks[$index] ?? [];
            if (empty($leadIds)) {
                continue;
            }

            CustomerServiceLead::whereIn('id', $leadIds)->update([
                'assigned_to_user_id' => $agent->id,
                'assigned_at' => now(),
                'assignment_cycle' => $assignmentCycle,
            ]);

            $assignedCount += count($leadIds);
        }

        return [
            'agents_count' => $agents->count(),
            'assigned_count' => $assignedCount,
            'remaining_unassigned_count' => $this->preferredUnassignedLeadQuery()->count(),
            'per_agent' => $perAgent,
        ];
    }

    protected function assignUnassignedLeadsAcrossActiveAgents(?int $perAgent = null): array
    {
        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->get();
        if ($agents->isEmpty()) {
            return [
                'agents_count' => 0,
                'assigned_count' => 0,
                'remaining_unassigned_count' => $this->unassignedLeadQuery()->count(),
                'per_agent' => $perAgent,
            ];
        }

        $pool = $this->unassignedLeadQuery()->get()->values();
        if ($pool->isEmpty()) {
            return [
                'agents_count' => $agents->count(),
                'assigned_count' => 0,
                'remaining_unassigned_count' => 0,
                'per_agent' => $perAgent,
            ];
        }

        $assignmentCycle = max((int) CustomerServiceLead::max('assignment_cycle'), 1);
        $chunks = array_fill(0, $agents->count(), []);
        $limitPerAgent = !is_null($perAgent) && $perAgent > 0 ? $perAgent : null;

        $agentIndexes = $agents->keys()->values();
        $assignedPerAgent = array_fill(0, $agents->count(), 0);
        $cursor = 0;

        foreach ($pool as $lead) {
            $attempts = 0;
            while ($attempts < $agents->count()) {
                $index = $agentIndexes[$cursor % $agents->count()];
                $cursor++;
                $attempts++;

                if (!is_null($limitPerAgent) && $assignedPerAgent[$index] >= $limitPerAgent) {
                    continue;
                }

                $chunks[$index][] = $lead->id;
                $assignedPerAgent[$index]++;
                break;
            }
        }

        $assignedCount = 0;
        foreach ($agents->values() as $index => $agent) {
            $leadIds = $chunks[$index] ?? [];
            if (empty($leadIds)) {
                continue;
            }

            CustomerServiceLead::whereIn('id', $leadIds)->update([
                'assigned_to_user_id' => $agent->id,
                'assigned_at' => now(),
                'assignment_cycle' => $assignmentCycle,
            ]);

            $assignedCount += count($leadIds);
        }

        return [
            'agents_count' => $agents->count(),
            'assigned_count' => $assignedCount,
            'remaining_unassigned_count' => $this->unassignedLeadQuery()->count(),
            'per_agent' => $perAgent,
        ];
    }

    protected function reassignRedistributableLeadsAcrossActiveAgents(?int $perAgent = null): array
    {
        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->get();
        if ($agents->isEmpty()) {
            return [
                'cycle' => max((int) CustomerServiceLead::max('assignment_cycle'), 1),
                'agents_count' => 0,
                'assigned_count' => 0,
                'remaining_unassigned_count' => $this->redistributionEligibleLeadQuery()->count(),
                'per_agent' => $perAgent,
            ];
        }

        $pool = $this->redistributionEligibleLeadQuery()->get()->values();
        if ($pool->isEmpty()) {
            return [
                'cycle' => max((int) CustomerServiceLead::max('assignment_cycle'), 1),
                'agents_count' => $agents->count(),
                'assigned_count' => 0,
                'remaining_unassigned_count' => 0,
                'per_agent' => $perAgent,
            ];
        }

        $assignmentCycle = max((int) CustomerServiceLead::max('assignment_cycle'), 0) + 1;
        $chunks = array_fill(0, $agents->count(), []);
        $limitPerAgent = !is_null($perAgent) && $perAgent > 0 ? $perAgent : null;
        $assignedPerAgent = array_fill(0, $agents->count(), 0);
        $cursor = 0;

        foreach ($pool as $lead) {
            $attempts = 0;
            while ($attempts < $agents->count()) {
                $index = $cursor % $agents->count();
                $cursor++;
                $attempts++;

                if (!is_null($limitPerAgent) && $assignedPerAgent[$index] >= $limitPerAgent) {
                    continue;
                }

                $chunks[$index][] = $lead->id;
                $assignedPerAgent[$index]++;
                break;
            }
        }

        $assignedCount = 0;
        foreach ($agents->values() as $index => $agent) {
            $leadIds = $chunks[$index] ?? [];
            if (empty($leadIds)) {
                continue;
            }

            CustomerServiceLead::whereIn('id', $leadIds)->update([
                'assigned_to_user_id' => $agent->id,
                'assigned_at' => now(),
                'assignment_cycle' => $assignmentCycle,
            ]);

            $assignedCount += count($leadIds);
        }

        return [
            'cycle' => $assignmentCycle,
            'agents_count' => $agents->count(),
            'assigned_count' => $assignedCount,
            'remaining_unassigned_count' => 0,
            'per_agent' => $perAgent,
        ];
    }

    protected function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $this->normalizeArabicDigits((string)$value));

        if (str_starts_with($digits, '0020') && strlen($digits) >= 14) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0' . $digits;
        }

        if (strlen($digits) > 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, -11);
        }

        return $digits;
    }

    protected function userNationalIdProtectionReasons(User $user): array
    {
        $reasons = [];

        if ((int) ($user->credit_applications_count ?? 0) > 0) {
            $reasons[] = 'لديه طلب تمويل مسجل بالفعل';
        }

        if ((int) ($user->orders_count ?? 0) > 0) {
            $reasons[] = 'لديه فواتير أو طلبات مسجلة بالفعل';
        }

        $lead = $user->customerServiceLead;
        if (!blank($lead?->prospect_national_id_number)) {
            $reasons[] = 'تم حفظ الرقم القومي في متابعة خدمة العملاء';
        }

        foreach ($user->creditApplications as $application) {
            if (!blank($application->national_id_number)) {
                $reasons[] = 'يوجد رقم قومي محفوظ في طلب اشتري بالآجل';
            }

            if ($application->getFirstMedia('national_id_front_document')) {
                $reasons[] = 'تم رفع صورة البطاقة الأمامية';
            }

            if ($application->getFirstMedia('national_id_back_document')) {
                $reasons[] = 'تم رفع صورة البطاقة الخلفية';
            }
        }

        return array_values(array_unique($reasons));
    }

    protected function normalizeArabicDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    protected function normalizeWorkbookValue(mixed $value): ?string
    {
        $normalized = trim((string)$value);
        return $normalized !== '' ? $normalized : null;
    }

    protected function extractWorkbookValue(array $row, array $candidates): ?string
    {
        $normalizedMap = [];
        foreach ($row as $key => $value) {
            $normalizedMap[$this->normalizeHeaderName((string) $key)] = $value;
        }

        foreach ($candidates as $candidate) {
            $value = $normalizedMap[$this->normalizeHeaderName($candidate)] ?? null;
            $normalized = $this->normalizeWorkbookValue($value);
            if (!blank($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    protected function extractWorkbookNumericValue(array $row, array $candidates): ?float
    {
        $value = $this->extractWorkbookValue($row, $candidates);
        if (blank($value)) {
            return null;
        }

        $numeric = preg_replace('/[^0-9.\-]/', '', $this->normalizeArabicDigits((string) $value));
        return $numeric === '' ? null : (float) $numeric;
    }

    protected function normalizeHeaderName(string $value): string
    {
        $value = mb_strtolower(trim($this->normalizeArabicDigits($value)));
        $value = preg_replace('/[\s\-_]+/u', ' ', $value);
        return trim((string) $value);
    }

    protected function sheetRows($spreadsheet, string $sheetName): array
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            return [];
        }

        $header = array_map(fn ($item) => trim((string)$item), $rows[0]);
        $dataRows = [];
        foreach (array_slice($rows, 1) as $row) {
            $assoc = [];
            foreach ($header as $index => $key) {
                if ($key === '') {
                    $key = 'Unnamed: ' . $index;
                }
                $assoc[$key] = $row[$index] ?? null;
            }

            $nonEmpty = array_filter($assoc, fn ($value) => $value !== null && trim((string)$value) !== '');
            if (!empty($nonEmpty)) {
                $dataRows[] = $assoc;
            }
        }

        return $dataRows;
    }

    protected function fillUserContactFields(User $user, array $data): void
    {
        $dirty = false;

        foreach (['address', 'city', 'area', 'latitude', 'longitude', 'distribution_route'] as $field) {
            if (blank($user->{$field}) && !blank($data[$field] ?? null)) {
                $user->{$field} = $data[$field];
                $dirty = true;
            }
        }

        if (blank($user->name) && !blank($data['name'] ?? null)) {
            $user->name = $data['name'];
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }
    }

    protected function syncApplicationMedia(
        CreditApplication $application,
        UploadedFile $front,
        ?UploadedFile $back = null,
        array $commercialRegisterDocuments = [],
        array $taxCardDocuments = [],
        array $rentContractDocuments = [],
        array $utilityBillDocuments = [],
        array $additionalDocuments = []
    ): void
    {
        $application->clearMediaCollection('national_id_front_document');
        $application->addMedia($front)->toMediaCollection('national_id_front_document');

        if ($back) {
            $application->clearMediaCollection('national_id_back_document');
            $application->addMedia($back)->toMediaCollection('national_id_back_document');
        }

        if (!empty($commercialRegisterDocuments)) {
            $application->clearMediaCollection('commercial_register_documents');
            foreach ($commercialRegisterDocuments as $commercialRegisterDocument) {
                if ($commercialRegisterDocument instanceof UploadedFile) {
                    $application->addMedia($commercialRegisterDocument)->toMediaCollection('commercial_register_documents');
                }
            }
        }

        if (!empty($taxCardDocuments)) {
            $application->clearMediaCollection('tax_card_document');
            foreach ($taxCardDocuments as $taxCardDocument) {
                if ($taxCardDocument instanceof UploadedFile) {
                    $application->addMedia($taxCardDocument)->toMediaCollection('tax_card_document');
                }
            }
        }

        if (!empty($rentContractDocuments)) {
            $application->clearMediaCollection('rent_contract_document');
            foreach ($rentContractDocuments as $rentContractDocument) {
                if ($rentContractDocument instanceof UploadedFile) {
                    $application->addMedia($rentContractDocument)->toMediaCollection('rent_contract_document');
                }
            }
        }

        if (!empty($utilityBillDocuments)) {
            $application->clearMediaCollection('utility_bill_document');
            foreach ($utilityBillDocuments as $utilityBillDocument) {
                if ($utilityBillDocument instanceof UploadedFile) {
                    $application->addMedia($utilityBillDocument)->toMediaCollection('utility_bill_document');
                }
            }
        }

        if (!empty($additionalDocuments)) {
            $application->clearMediaCollection('additional_documents');
            foreach ($additionalDocuments as $additionalDocument) {
                if ($additionalDocument instanceof UploadedFile) {
                    $application->addMedia($additionalDocument)->toMediaCollection('additional_documents');
                }
            }
        }
    }

    protected function safeNotify(User $user, object $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $throwable) {
            Log::warning('Customer service CRM notification failed', [
                'user_id' => $user->id,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
