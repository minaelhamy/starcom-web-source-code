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
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class CustomerServiceLeadService
{
    public const TAB_ALL = 'all';
    public const TAB_CALLBACK = 'callback';
    public const TAB_WAITING = 'waiting';
    public const TAB_REFUSED = 'refused';

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
            'user.creditApplications',
            'assignedAgent',
            'activities.actor',
        ]);
        $lead->status_label = self::statusLabels()[$lead->status ?: CustomerServiceLeadStatus::NOT_APPROACHED] ?? $lead->status;

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

                $this->syncApplicationMedia($application, $request->file('national_id_front_document'), $request->file('national_id_back_document'));

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

    public function dashboardSummary(): array
    {
        $actor = Auth::user();

        if ($actor->hasRole(EnumRole::CUSTOMER_SERVICE)) {
            $base = $this->baseLeadQuery($actor);
            $all = (clone $base)->get();

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
                'upcoming_callbacks' => $all->filter(fn (CustomerServiceLead $lead) => $lead->next_follow_up_at !== null)
                    ->sortBy('next_follow_up_at')
                    ->take(8)
                    ->values()
                    ->map(fn (CustomerServiceLead $lead) => $this->dashboardLeadPayload($lead)),
            ];
        }

        $base = $this->baseLeadQuery($actor, true);
        $all = (clone $base)->get();
        $agents = User::role(EnumRole::CUSTOMER_SERVICE)->where('status', 5)->count();

        return [
            'mode' => 'manager',
            'total_open_leads' => $all->count(),
            'active_agents_count' => $agents,
            'unassigned_count' => $all->whereNull('assigned_to_user_id')->count(),
            'callbacks_count' => $all->whereIn('status', self::callbackStatuses())->count(),
            'waiting_documents_count' => $all->whereIn('status', self::waitingStatuses())->count(),
            'refused_count' => $all->whereIn('status', self::refusedStatuses())->count(),
            'not_approached_count' => $all->where('status', CustomerServiceLeadStatus::NOT_APPROACHED)->count() + $all->whereNull('status')->count(),
            'today_updates_count' => CustomerServiceLeadActivity::whereDate('created_at', today())->count(),
            'week_updates_count' => CustomerServiceLeadActivity::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'applications_submitted_count' => CreditApplication::whereNotNull('submitted_by_customer_service_user_id')->count(),
            'status_breakdown' => $this->statusBreakdown($all),
            'top_agents' => $this->agentPerformanceCollection(request()),
        ];
    }

    public function reportSummary(Request $request): array
    {
        $actor = Auth::user();
        if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'agents' => $this->agentPerformanceCollection($request),
        ];
    }

    public function redistribute(int $perAgent = 300): array
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
            $cycle = ((int)CustomerServiceLead::max('assignment_cycle')) + 1;
            $eligible = $this->redistributableLeadQuery()->get()->groupBy('priority_order');

            CustomerServiceLead::whereIn('id', $eligible->flatten()->pluck('id'))
                ->update([
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'assignment_cycle' => $cycle,
                ]);

            $pool = collect();
            foreach ($eligible->sortKeys() as $group) {
                $pool = $pool->concat($group->shuffle());
            }

            $assigned = 0;
            foreach ($agents as $agentIndex => $agent) {
                $chunk = $pool->slice($agentIndex * $perAgent, $perAgent);
                if ($chunk->isEmpty()) {
                    continue;
                }

                CustomerServiceLead::whereIn('id', $chunk->pluck('id'))->update([
                    'assigned_to_user_id' => $agent->id,
                    'assigned_at' => now(),
                    'assignment_cycle' => $cycle,
                ]);

                $assigned += $chunk->count();
            }

            return [
                'cycle' => $cycle,
                'agents_count' => $agents->count(),
                'per_agent' => $perAgent,
                'assigned_count' => $assigned,
                'remaining_unassigned_count' => max($pool->count() - $assigned, 0),
            ];
        });
    }

    public function assignFreshLeadsToAgent(User $agent, int $limit = 300): array
    {
        if (!$agent->hasRole(EnumRole::CUSTOMER_SERVICE) || (int)$agent->status !== 5) {
            return [
                'agent_id' => $agent->id,
                'assigned_count' => 0,
                'current_assigned_count' => 0,
                'remaining_capacity' => $limit,
            ];
        }

        return DB::transaction(function () use ($agent, $limit) {
            $currentAssignedCount = CustomerServiceLead::query()
                ->where('assigned_to_user_id', $agent->id)
                ->whereDoesntHave('user.creditApplications')
                ->count();

            $remainingCapacity = max($limit - $currentAssignedCount, 0);
            if ($remainingCapacity === 0) {
                return [
                    'agent_id' => $agent->id,
                    'assigned_count' => 0,
                    'current_assigned_count' => $currentAssignedCount,
                    'remaining_capacity' => 0,
                ];
            }

            $assignmentCycle = max((int)CustomerServiceLead::max('assignment_cycle'), 1);

            $leadIds = $this->unassignedLeadQuery()
                ->orderBy('priority_order')
                ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('next_follow_up_at')
                ->latest('updated_at')
                ->limit($remainingCapacity)
                ->pluck('id');

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
                'remaining_capacity' => max($limit - ($currentAssignedCount + $leadIds->count()), 0),
            ];
        });
    }

    public function releaseAgentLeads(User $agent): int
    {
        return CustomerServiceLead::query()
            ->where('assigned_to_user_id', $agent->id)
            ->whereDoesntHave('user.creditApplications')
            ->update([
                'assigned_to_user_id' => null,
                'assigned_at' => null,
            ]);
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
            'user.creditApplications',
            'assignedAgent',
        ])->whereHas('user', function (Builder $userQuery) {
            $userQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('id', EnumRole::CUSTOMER))
                ->whereNull('deleted_at');
        });

        $query->whereDoesntHave('user.creditApplications');

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
            ->whereDoesntHave('user.creditApplications');
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

        if ($lead->user && $lead->user->creditApplications()->exists()) {
            throw new Exception('هذا العميل قام بالفعل بالتقديم داخل النظام، لذلك لم يعد ضمن قائمة خدمة العملاء.', 422);
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

    protected function agentPerformanceCollection(Request $request): array
    {
        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : now()->subDays(6)->startOfDay();
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();

        return User::role(EnumRole::CUSTOMER_SERVICE)
            ->where('status', 5)
            ->get()
            ->map(function (User $agent) use ($dateFrom, $dateTo) {
                $assignedLeads = $agent->assignedCustomerServiceLeads()->whereDoesntHave('user.creditApplications')->get();
                $activities = $agent->customerServiceLeadActivities()->whereBetween('created_at', [$dateFrom, $dateTo])->get();

                return [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'assigned_leads_count' => $assignedLeads->count(),
                    'not_approached_count' => $assignedLeads->where('status', CustomerServiceLeadStatus::NOT_APPROACHED)->count() + $assignedLeads->whereNull('status')->count(),
                    'waiting_documents_count' => $assignedLeads->whereIn('status', self::waitingStatuses())->count(),
                    'callbacks_count' => $assignedLeads->whereIn('status', self::callbackStatuses())->count(),
                    'refused_count' => $assignedLeads->whereIn('status', self::refusedStatuses())->count(),
                    'period_updates_count' => $activities->count(),
                    'period_applications_submitted_count' => CreditApplication::query()
                        ->where('submitted_by_customer_service_user_id', $agent->id)
                        ->whereBetween('submitted_by_customer_service_at', [$dateFrom, $dateTo])
                        ->count(),
                    'total_applications_submitted_count' => CreditApplication::query()
                        ->where('submitted_by_customer_service_user_id', $agent->id)
                        ->count(),
                    'today_updates_count' => $agent->customerServiceLeadActivities()->whereDate('created_at', today())->count(),
                    'week_updates_count' => $agent->customerServiceLeadActivities()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                ];
            })
            ->values()
            ->all();
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

    protected function syncApplicationMedia(CreditApplication $application, UploadedFile $front, ?UploadedFile $back = null): void
    {
        $application->clearMediaCollection('national_id_front_document');
        $application->addMedia($front)->toMediaCollection('national_id_front_document');

        if ($back) {
            $application->clearMediaCollection('national_id_back_document');
            $application->addMedia($back)->toMediaCollection('national_id_back_document');
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
