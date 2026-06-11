<?php

namespace App\Services;

use App\Enums\CreditApplicationStatus;
use App\Enums\CreditFacilityStatus;
use App\Enums\Role as EnumRole;
use App\Http\Requests\CreditApplicationDecisionRequest;
use App\Http\Requests\CreditApplicationIdentityRequest;
use App\Http\Requests\CreditApplicationNoteRequest;
use App\Http\Requests\CreditFacilityAssignmentRequest;
use App\Http\Requests\CreditFacilityContractRequest;
use App\Http\Requests\CreditApplicationStoreRequest;
use App\Http\Requests\CreditApplicationUpdateRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Models\CreditApplication;
use App\Models\CreditApplicationNote;
use App\Models\CreditFacility;
use App\Models\User;
use App\Notifications\CreditApplicationApprovedNotification;
use App\Notifications\CreditApplicationDeclinedNotification;
use App\Notifications\NewCreditApplicationSubmittedNotification;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\WalletTransaction;

class CreditApplicationService
{
    public function lenderOpportunitiesQuery(User $actor)
    {
        $institutionId = $this->resolveInstitutionUserId($actor);

        return CreditApplication::with([
            'user',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ])
            ->where(function ($query) use ($institutionId) {
                $query->where('status', CreditApplicationStatus::PENDING)
                    ->orWhere(function ($reopenQuery) use ($institutionId) {
                        $reopenQuery->whereIn('status', [
                            CreditApplicationStatus::PENDING_APPROVAL,
                            CreditApplicationStatus::DECLINED,
                        ])->whereHas('facilities', function ($facilityQuery) use ($institutionId) {
                            $facilityQuery->where('financial_institution_user_id', $institutionId);
                        });
                    });
            })
            ->whereDoesntHave('facilities', function ($facilityQuery) {
                $facilityQuery->where('status', CreditFacilityStatus::APPROVED);
            });
    }

    public function customerList(PaginateRequest $request)
    {
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';

        return CreditApplication::with([
            'user',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ])
            ->where('user_id', Auth::id())
            ->latest()
            ->$method($methodValue);
    }

    public function customerStore(CreditApplicationStoreRequest $request): CreditApplication
    {
        try {
            if (
                CreditApplication::where('user_id', Auth::id())
                    ->whereIn('status', [
                        CreditApplicationStatus::PENDING,
                        CreditApplicationStatus::PENDING_APPROVAL,
                        CreditApplicationStatus::DECLINED,
                    ])
                    ->exists()
            ) {
                throw new Exception(trans('all.message.credit_application_pending_exists'), 422);
            }

            $application = CreditApplication::create([
                'user_id' => Auth::id(),
                'full_name' => $request->full_name,
                'national_id_number' => $request->national_id_number,
                'status'  => CreditApplicationStatus::PENDING,
                'notes'   => $request->notes,
            ]);

            if ($request->hasFile('national_id_front_document')) {
                $application->addMedia($request->file('national_id_front_document'))->toMediaCollection('national_id_front_document');
            }

            if ($request->hasFile('national_id_back_document')) {
                $application->addMedia($request->file('national_id_back_document'))->toMediaCollection('national_id_back_document');
            }

            foreach ($request->file('commercial_register_documents', []) as $commercialRegisterDocument) {
                $application->addMedia($commercialRegisterDocument)->toMediaCollection('commercial_register_documents');
            }

            if ($request->hasFile('tax_card_document')) {
                $application->addMedia($request->file('tax_card_document'))->toMediaCollection('tax_card_document');
            }

            User::role(EnumRole::FINANCIAL_INSTITUTION)->get()->each(function (User $institutionUser) use ($application) {
                $this->safeNotify($institutionUser, new NewCreditApplicationSubmittedNotification($application));
            });

            return $application->load([
                'user',
                'submittedByCustomerService',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'facilities.institution.financialInstitutionProfile',
                'facilities.employee',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function customerUpdate(CreditApplication $creditApplication, CreditApplicationUpdateRequest $request): CreditApplication
    {
        try {
            $actor = Auth::user();

            if ((int)$creditApplication->user_id !== (int)$actor->id) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditApplication->status !== CreditApplicationStatus::PENDING_APPROVAL) {
                throw new Exception('يمكن تعديل الطلب فقط عندما تكون حالته قيد التعديل.', 422);
            }

            $creditApplication->full_name = $request->full_name;
            $creditApplication->national_id_number = $request->national_id_number;
            $creditApplication->notes = $request->notes;
            $creditApplication->status = CreditApplicationStatus::PENDING;
            $creditApplication->save();

            if ($request->hasFile('national_id_front_document')) {
                $creditApplication->clearMediaCollection('national_id_front_document');
                $creditApplication->addMedia($request->file('national_id_front_document'))->toMediaCollection('national_id_front_document');
            }

            if ($request->hasFile('national_id_back_document')) {
                $creditApplication->clearMediaCollection('national_id_back_document');
                $creditApplication->addMedia($request->file('national_id_back_document'))->toMediaCollection('national_id_back_document');
            }

            if ($request->hasFile('commercial_register_documents')) {
                $creditApplication->clearMediaCollection('commercial_register_documents');
                foreach ($request->file('commercial_register_documents', []) as $commercialRegisterDocument) {
                    $creditApplication->addMedia($commercialRegisterDocument)->toMediaCollection('commercial_register_documents');
                }
            }

            if ($request->hasFile('tax_card_document')) {
                $creditApplication->clearMediaCollection('tax_card_document');
                $creditApplication->addMedia($request->file('tax_card_document'))->toMediaCollection('tax_card_document');
            }

            return $creditApplication->load([
                'user',
                'submittedByCustomerService',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'facilities.institution.financialInstitutionProfile',
                'facilities.employee',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function customerDestroy(CreditApplication $creditApplication): void
    {
        $actor = Auth::user();

        if ((int)$creditApplication->user_id !== (int)$actor->id) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        $this->destroyApplication($creditApplication, false);
    }

    public function updateIdentity(CreditApplication $creditApplication, CreditApplicationIdentityRequest $request): CreditApplication
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            $creditApplication->full_name = $request->full_name;
            $creditApplication->national_id_number = $request->national_id_number;
            $creditApplication->save();

            if ($request->hasFile('national_id_front_document')) {
                $creditApplication->clearMediaCollection('national_id_front_document');
                $creditApplication->addMedia($request->file('national_id_front_document'))->toMediaCollection('national_id_front_document');
            }

            if ($request->hasFile('national_id_back_document')) {
                $creditApplication->clearMediaCollection('national_id_back_document');
                $creditApplication->addMedia($request->file('national_id_back_document'))->toMediaCollection('national_id_back_document');
            }

            if (
                $request->boolean('return_to_review') &&
                $creditApplication->status === CreditApplicationStatus::PENDING_APPROVAL
            ) {
                $creditApplication->status = CreditApplicationStatus::PENDING;
                $creditApplication->save();
            }

            return $creditApplication->load([
                'user',
                'submittedByCustomerService',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'facilities.institution.financialInstitutionProfile',
                'facilities.employee',
            ]);
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
        $term = trim((string) $request->get('term', ''));

        $query = CreditApplication::with([
            'user',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ]);

        if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            $query = $this->lenderOpportunitiesQuery($actor);
        } else {
            $query->where('status', '!=', CreditApplicationStatus::APPROVED);
        }

        if ($term !== '') {
            $normalizedTerm = preg_replace('/\s+/', '', $term);

            $query->where(function ($filterQuery) use ($term, $normalizedTerm) {
                $filterQuery->whereHas('user', function ($userQuery) use ($term, $normalizedTerm) {
                    $userQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $normalizedTerm . '%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')), ' ', '') LIKE ?", ['%' . $normalizedTerm . '%']);
                })->orWhere('full_name', 'like', '%' . $term . '%')
                    ->orWhere('national_id_number', 'like', '%' . $normalizedTerm . '%');
            });
        }

        return $query->latest()->$method($methodValue);
    }

    public function portfolioList(PaginateRequest $request)
    {
        $actor = Auth::user();
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
        $term = trim((string) $request->get('term', ''));

        $query = $this->portfolioQuery($actor);

        if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            $query->where('status', CreditFacilityStatus::APPROVED);
        }

        if ($term !== '') {
            $normalizedTerm = preg_replace('/\s+/', '', $term);

            $query->where(function ($filterQuery) use ($term, $normalizedTerm) {
                $filterQuery->whereHas('user', function ($userQuery) use ($term, $normalizedTerm) {
                    $userQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $normalizedTerm . '%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')), ' ', '') LIKE ?", ['%' . $normalizedTerm . '%']);
                })->orWhereHas('application', function ($applicationQuery) use ($term, $normalizedTerm) {
                    $applicationQuery->where('full_name', 'like', '%' . $term . '%')
                        ->orWhere('national_id_number', 'like', '%' . $normalizedTerm . '%');
                });
            });
        }

        return $query->latest()->$method($methodValue);
    }

    public function show(CreditApplication $creditApplication): CreditApplication
    {
        $actor = Auth::user();

        if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            $institutionId = $this->resolveInstitutionUserId($actor);
            if ($creditApplication->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditApplication->status === CreditApplicationStatus::PENDING) {
                return $creditApplication->load([
                    'user',
                    'submittedByCustomerService',
                    'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                    'facilities.institution.financialInstitutionProfile',
                    'facilities.employee',
                ]);
            }

            if (
                !in_array($creditApplication->status, [CreditApplicationStatus::PENDING_APPROVAL, CreditApplicationStatus::DECLINED], true) ||
                !$creditApplication->facilities()->where('financial_institution_user_id', $institutionId)->exists()
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        }

        return $creditApplication->load([
            'user',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ]);
    }

    public function showFacility(CreditFacility $creditFacility): CreditFacility
    {
        $actor = Auth::user();

        if (
            $actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) &&
            (int)$creditFacility->financial_institution_user_id !== (int)$this->resolveInstitutionUserId($actor)
        ) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        return $creditFacility->load([
            'user',
            'user.latestAddress',
            'application.user',
            'application.notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'application.facilities.institution.financialInstitutionProfile',
            'application.facilities.employee',
            'institution.financialInstitutionProfile',
            'employee',
        ]);
    }

    public function assignmentOptions(): array
    {
        $institutions = User::with('financialInstitutionProfile')
            ->role(EnumRole::FINANCIAL_INSTITUTION)
            ->whereHas('financialInstitutionProfile')
            ->orderBy('name')
            ->get();

        $employees = User::role(EnumRole::FINANCIAL_INSTITUTION)
            ->with(['financialInstitutionOwner.financialInstitutionProfile'])
            ->whereNotNull('financial_institution_owner_user_id')
            ->orderBy('name')
            ->get();

        return [
            'institutions' => $institutions->map(function (User $institution) {
                return [
                    'id' => $institution->id,
                    'name' => $institution->name,
                    'company_name' => $institution->financialInstitutionProfile?->company_name ?: $institution->name,
                ];
            })->values(),
            'employees' => $employees->map(function (User $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'institution_owner_user_id' => $employee->financial_institution_owner_user_id,
                    'institution_company_name' => $employee->financialInstitutionOwner?->financialInstitutionProfile?->company_name,
                ];
            })->values(),
        ];
    }

    public function assignFacility(CreditFacility $creditFacility, CreditFacilityAssignmentRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            return DB::transaction(function () use ($creditFacility, $request) {
                $facility = CreditFacility::with(['institution', 'employee'])->lockForUpdate()->findOrFail($creditFacility->id);
                $institution = User::with('financialInstitutionProfile')->findOrFail((int)$request->financial_institution_user_id);
                $employee = $request->filled('financial_institution_employee_user_id')
                    ? User::findOrFail((int)$request->financial_institution_employee_user_id)
                    : $institution;

                $this->assertValidInstitutionAssignment($institution, $employee);

                $facility->financial_institution_user_id = $institution->id;
                $facility->financial_institution_employee_user_id = $employee->id;
                $facility->save();

                WalletTransaction::where('credit_facility_id', $facility->id)->update([
                    'financial_institution_user_id' => $institution->id,
                ]);

                return $facility->load([
                    'user',
                    'user.latestAddress',
                    'application.user',
                    'application.notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                    'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                    'application.facilities.institution.financialInstitutionProfile',
                    'application.facilities.employee',
                    'institution.financialInstitutionProfile',
                    'employee',
                ]);
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function resetApproval(CreditFacility $creditFacility): CreditApplication
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditFacility->status !== CreditFacilityStatus::APPROVED) {
                throw new Exception('يمكن للإدارة إلغاء الاعتماد فقط بعد موافقة جهة تمويل.', 422);
            }

            if ((float)$creditFacility->utilized_amount > 0 || $creditFacility->orderAllocations()->exists()) {
                throw new Exception('لا يمكن إلغاء هذا الاعتماد بعد استخدامه في طلبات شراء.', 422);
            }

            DB::transaction(function () use ($creditFacility) {
                $facility = CreditFacility::with(['application', 'user'])->lockForUpdate()->findOrFail($creditFacility->id);
                $user = User::lockForUpdate()->findOrFail($facility->user_id);
                $reversalAmount = (float)$facility->available_amount;

                if ($reversalAmount <= 0) {
                    throw new Exception('لا يوجد رصيد متاح لإرجاعه من هذا الاعتماد.', 422);
                }

                if ((float)$user->balance < $reversalAmount) {
                    throw new Exception('لا يمكن إلغاء الاعتماد حالياً لأن رصيد المحفظة أقل من الرصيد المعتمد.', 422);
                }

                $before = (float)$user->balance;
                $user->balance = $before - $reversalAmount;
                $user->save();

                WalletTransaction::create([
                    'user_id'                       => $user->id,
                    'financial_institution_user_id' => $facility->financial_institution_user_id,
                    'credit_application_id'         => $facility->credit_application_id,
                    'credit_facility_id'            => $facility->id,
                    'type'                          => 'facility_reset',
                    'direction'                     => 'debit',
                    'amount'                        => $reversalAmount,
                    'balance_before'                => $before,
                    'balance_after'                 => (float)$user->balance,
                    'description'                   => 'تم إلغاء اعتماد الجهة التمويلية وإعادة الطلب إلى قائمة المراجعة',
                    'meta'                          => ['reset_by_admin' => true],
                ]);

                $application = $facility->application;
                $facility->notesHistory()->delete();
                $facility->delete();
                $this->refreshApplicationStatus($application);
            });

            return CreditApplication::with([
                'user',
                'user.latestAddress',
                'submittedByCustomerService',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'facilities.institution.financialInstitutionProfile',
                'facilities.employee',
            ])
                ->findOrFail($creditFacility->credit_application_id);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function adminDestroy(CreditApplication $creditApplication): void
    {
        $actor = Auth::user();
        if (!$actor->hasRole(EnumRole::ADMIN) && !$actor->hasRole(EnumRole::MANAGER)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        $this->destroyApplication($creditApplication, true);
    }

    public function uploadFacilityContracts(CreditFacility $creditFacility, CreditFacilityContractRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();

            if (
                !$actor->hasRole(EnumRole::ADMIN) &&
                !$actor->hasRole(EnumRole::MANAGER) &&
                !$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if (
                $actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) &&
                (int) $creditFacility->financial_institution_user_id !== (int) $this->resolveInstitutionUserId($actor)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditFacility->status !== CreditFacilityStatus::APPROVED) {
                throw new Exception('يمكن رفع العقود فقط بعد اعتماد التمويل.', 422);
            }

            foreach ($request->file('contract_documents', []) as $contractDocument) {
                $creditFacility->addMedia($contractDocument)->toMediaCollection('facility_contract_documents');
            }

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'application.facilities.institution.financialInstitutionProfile',
                'application.facilities.employee',
                'institution.financialInstitutionProfile',
                'employee',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function approve(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) && !$actor->hasRole(EnumRole::ADMIN)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            [$institution, $employee] = $this->resolveAssignmentActors($actor, $request);

            if (
                !in_array($creditApplication->status, [CreditApplicationStatus::PENDING, CreditApplicationStatus::PENDING_APPROVAL, CreditApplicationStatus::DECLINED], true) ||
                $creditApplication->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()
            ) {
                throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
            }

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingFacility && $existingFacility->status === CreditFacilityStatus::APPROVED) {
                    throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
                }

                if ($application->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()) {
                    throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
                }

                if ($existingFacility) {
                    return $this->approveExistingFacility(
                        $existingFacility,
                        $application,
                        $institution,
                        $employee,
                        (float)$request->approved_amount,
                        (int)$request->duration_days,
                        $request->notes
                    );
                }

                return app(WalletService::class)->creditByFacility(
                    $application->user,
                    $application,
                    $institution,
                    (float)$request->approved_amount,
                    'تمت إضافة رصيد إلى المحفظة',
                    [
                        'duration_days' => (int)$request->duration_days,
                        'notes'         => $request->notes,
                        'financial_institution_employee_user_id' => $employee->id,
                    ]
                );
            });

            $this->createFacilityNote($creditApplication, $facility, $actor, $request->notes);

            $this->refreshApplicationStatus($creditApplication);
            $this->safeNotify($creditApplication->user, new CreditApplicationApprovedNotification($creditApplication, $facility));

            return $facility->load([
                'user',
                'institution.financialInstitutionProfile',
                'employee',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'application',
            ]);
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

            [$institution, $employee] = $this->resolveAssignmentActors($actor, $request, false);

            if (
                !in_array($creditApplication->status, [CreditApplicationStatus::PENDING, CreditApplicationStatus::PENDING_APPROVAL, CreditApplicationStatus::DECLINED], true) ||
                $creditApplication->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()
            ) {
                throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
            }

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingFacility && $existingFacility->status === CreditFacilityStatus::APPROVED) {
                    throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
                }

                if ($application->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()) {
                    throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
                }

                if ($existingFacility) {
                    $existingFacility->financial_institution_employee_user_id = $employee->id;
                    $existingFacility->status = CreditFacilityStatus::DECLINED;
                    $existingFacility->approved_amount = 0;
                    $existingFacility->available_amount = 0;
                    $existingFacility->utilized_amount = 0;
                    $existingFacility->duration_days = max(30, (int)$request->duration_days);
                    $existingFacility->starts_at = null;
                    $existingFacility->due_at = null;
                    $existingFacility->reviewed_at = now();
                    $existingFacility->notes = $request->decline_reason ?: $request->notes;
                    $existingFacility->save();

                    return $existingFacility;
                }

                return CreditFacility::create([
                    'credit_application_id'         => $application->id,
                    'user_id'                       => $application->user_id,
                    'financial_institution_user_id' => $institution->id,
                    'financial_institution_employee_user_id' => $employee->id,
                    'status'                        => CreditFacilityStatus::DECLINED,
                    'approved_amount'               => 0,
                    'available_amount'              => 0,
                    'utilized_amount'               => 0,
                    'duration_days'                 => max(30, (int)$request->duration_days),
                    'reviewed_at'                   => now(),
                    'notes'                         => $request->decline_reason ?: $request->notes,
                ]);
            });

            $this->createFacilityNote($creditApplication, $facility, $actor, $request->decline_reason ?: $request->notes);

            $this->refreshApplicationStatus($creditApplication);
            $this->safeNotify($creditApplication->user, new CreditApplicationDeclinedNotification($creditApplication, $facility));

            return $facility->load([
                'user',
                'institution.financialInstitutionProfile',
                'employee',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'application',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function markPendingApproval(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (!$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) && !$actor->hasRole(EnumRole::ADMIN)) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            [$institution, $employee] = $this->resolveAssignmentActors($actor, $request, false);
            $note = trim((string)($request->notes ?: $request->decline_reason));
            if ($note === '') {
                throw new Exception('يرجى كتابة الملاحظات المطلوبة قبل إرسال الطلب إلى قيد التعديل.', 422);
            }

            if (
                !in_array($creditApplication->status, [CreditApplicationStatus::PENDING, CreditApplicationStatus::PENDING_APPROVAL, CreditApplicationStatus::DECLINED], true) ||
                $creditApplication->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()
            ) {
                throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
            }

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $note, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

                if ($application->facilities()->where('status', CreditFacilityStatus::APPROVED)->exists()) {
                    throw new Exception('تمت مراجعة هذا الطلب بالفعل من جهة تمويل أخرى.', 422);
                }

                if ($existingFacility) {
                    $existingFacility->financial_institution_employee_user_id = $employee->id;
                    $existingFacility->status = CreditFacilityStatus::PENDING_APPROVAL;
                    $existingFacility->approved_amount = 0;
                    $existingFacility->available_amount = 0;
                    $existingFacility->utilized_amount = 0;
                    $existingFacility->duration_days = max(30, (int)$request->duration_days);
                    $existingFacility->starts_at = null;
                    $existingFacility->due_at = null;
                    $existingFacility->reviewed_at = now();
                    $existingFacility->notes = $note;
                    $existingFacility->save();
                    return $existingFacility;
                }

                return CreditFacility::create([
                    'credit_application_id' => $application->id,
                    'user_id' => $application->user_id,
                    'financial_institution_user_id' => $institution->id,
                    'financial_institution_employee_user_id' => $employee->id,
                    'status' => CreditFacilityStatus::PENDING_APPROVAL,
                    'approved_amount' => 0,
                    'available_amount' => 0,
                    'utilized_amount' => 0,
                    'duration_days' => max(30, (int)$request->duration_days),
                    'reviewed_at' => now(),
                    'notes' => $note,
                ]);
            });

            $this->createFacilityNote($creditApplication, $facility, $actor, $note);
            $this->refreshApplicationStatus($creditApplication);

            return $facility->load([
                'user',
                'institution.financialInstitutionProfile',
                'employee',
                'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
                'application',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function portfolioQuery(User $actor)
    {
        $query = CreditFacility::with(['user', 'application', 'institution.financialInstitutionProfile', 'employee']);

        if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            $query->where('financial_institution_user_id', $this->resolveInstitutionUserId($actor));
        }

        return $query;
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

    public function addFacilityNote(CreditFacility $creditFacility, CreditApplicationNoteRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();
            if (
                !$actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) &&
                !$actor->hasRole(EnumRole::ADMIN) &&
                !$actor->hasRole(EnumRole::MANAGER)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if (
                $actor->hasRole(EnumRole::FINANCIAL_INSTITUTION) &&
                (int)$creditFacility->financial_institution_user_id !== (int)$this->resolveInstitutionUserId($actor)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            DB::transaction(function () use ($creditFacility, $actor, $request) {
                $facility = CreditFacility::with('application')->lockForUpdate()->findOrFail($creditFacility->id);
                $note = trim((string)$request->note);

                $facility->notes = $note;
                $facility->save();

                $this->createFacilityNote($facility->application, $facility, $actor, $note);
            });

            return $this->showFacility($creditFacility);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    protected function refreshApplicationStatus(CreditApplication $creditApplication): void
    {
        $creditApplication->refresh();
        $facilities = $creditApplication->facilities;

        if ($facilities->where('status', CreditFacilityStatus::APPROVED)->count() > 0) {
            $creditApplication->status = CreditApplicationStatus::APPROVED;
        } elseif ($facilities->where('status', CreditFacilityStatus::PENDING_APPROVAL)->count() > 0) {
            $creditApplication->status = CreditApplicationStatus::PENDING_APPROVAL;
        } elseif ($facilities->count() > 0 && $facilities->where('status', CreditFacilityStatus::DECLINED)->count() === $facilities->count()) {
            $creditApplication->status = CreditApplicationStatus::DECLINED;
        } else {
            $creditApplication->status = CreditApplicationStatus::PENDING;
        }

        $creditApplication->save();
    }

    protected function destroyApplication(CreditApplication $creditApplication, bool $isAdmin): void
    {
        try {
            $creditApplication->loadMissing(['facilities.orderAllocations']);

            if ($creditApplication->facilities->where('status', CreditFacilityStatus::APPROVED)->count() > 0) {
                throw new Exception(
                    $isAdmin
                        ? 'لا يمكن حذف طلب تمت الموافقة عليه. قم أولاً بإلغاء الاعتماد ثم احذف الطلب.'
                        : 'لا يمكن حذف الطلب بعد الموافقة عليه من جهة تمويل.',
                    422
                );
            }

            if ($creditApplication->facilities->contains(function (CreditFacility $facility) {
                return (float)$facility->utilized_amount > 0 || $facility->orderAllocations->isNotEmpty();
            })) {
                throw new Exception('لا يمكن حذف هذا الطلب بعد ارتباطه بطلبات شراء.', 422);
            }

            DB::transaction(function () use ($creditApplication) {
                $application = CreditApplication::with(['facilities.orderAllocations'])->lockForUpdate()->findOrFail($creditApplication->id);

                foreach ($application->facilities as $facility) {
                    $facility->notesHistory()->delete();
                    $facility->orderAllocations()->delete();
                    WalletTransaction::where('credit_facility_id', $facility->id)->delete();
                    $facility->delete();
                }

                $application->notesHistory()->delete();
                WalletTransaction::where('credit_application_id', $application->id)->delete();
                $application->clearMediaCollection('national_id_front_document');
                $application->clearMediaCollection('national_id_back_document');
                $application->clearMediaCollection('commercial_register_documents');
                $application->clearMediaCollection('tax_card_document');
                $application->delete();
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
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

    protected function resolveInstitutionUserId(User $actor): int
    {
        return (int)($actor->resolvedFinancialInstitutionUserId() ?: $actor->id);
    }

    protected function resolveAssignmentActors(User $actor, CreditApplicationDecisionRequest $request, bool $requireInstitutionForAdmin = true): array
    {
        if ($actor->hasRole(EnumRole::ADMIN)) {
            if ($requireInstitutionForAdmin && !$request->filled('financial_institution_user_id')) {
                throw new Exception('يرجى اختيار جهة التمويل قبل الاعتماد.', 422);
            }

            $institution = $request->filled('financial_institution_user_id')
                ? User::with('financialInstitutionProfile')->findOrFail((int)$request->financial_institution_user_id)
                : null;
            $employee = $request->filled('financial_institution_employee_user_id')
                ? User::findOrFail((int)$request->financial_institution_employee_user_id)
                : $institution;

            if (!$institution) {
                throw new Exception('يرجى اختيار جهة التمويل.', 422);
            }

            $this->assertValidInstitutionAssignment($institution, $employee);

            return [$institution, $employee ?: $institution];
        }

        $institution = $actor->financialInstitutionOwner ?: $actor;
        $employee = $actor;

        return [$institution, $employee];
    }

    protected function assertValidInstitutionAssignment(User $institution, ?User $employee): void
    {
        if (!$institution->hasRole(EnumRole::FINANCIAL_INSTITUTION) || !$institution->financialInstitutionProfile) {
            throw new Exception('يرجى اختيار جهة تمويل صحيحة.', 422);
        }

        if (!$employee) {
            return;
        }

        if (!$employee->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            throw new Exception('يرجى اختيار موظف تابع لجهة تمويل.', 422);
        }

        if ((int)$employee->id === (int)$institution->id) {
            return;
        }

        if ($employee->financial_institution_owner_user_id && (int)$employee->financial_institution_owner_user_id !== (int)$institution->id) {
            throw new Exception('هذا الموظف مرتبط بجهة تمويل أخرى.', 422);
        }

        if ((int)$employee->financial_institution_owner_user_id !== (int)$institution->id) {
            $employee->financial_institution_owner_user_id = $institution->id;
            $employee->save();
        }
    }

    protected function createFacilityNote(CreditApplication $application, ?CreditFacility $facility, User $author, ?string $note): ?CreditApplicationNote
    {
        $note = trim((string)$note);
        if ($note === '') {
            return null;
        }

        return CreditApplicationNote::create([
            'credit_application_id' => $application->id,
            'credit_facility_id' => $facility?->id,
            'author_user_id' => $author->id,
            'note' => $note,
        ]);
    }

    protected function approveExistingFacility(
        CreditFacility $facility,
        CreditApplication $application,
        User $institution,
        User $employee,
        float $amount,
        int $durationDays,
        ?string $notes
    ): CreditFacility {
        $user = User::lockForUpdate()->findOrFail($application->user_id);

        $facility->financial_institution_employee_user_id = $employee->id;
        $facility->status = CreditFacilityStatus::APPROVED;
        $facility->approved_amount = $amount;
        $facility->available_amount = $amount;
        $facility->utilized_amount = 0;
        $facility->duration_days = $durationDays;
        $facility->starts_at = now();
        $facility->due_at = now()->addDays($durationDays);
        $facility->reviewed_at = now();
        $facility->notes = $notes;
        $facility->save();

        $before = (float)$user->balance;
        $user->balance = $before + $amount;
        $user->save();

        WalletTransaction::create([
            'user_id'                        => $user->id,
            'financial_institution_user_id'  => $institution->id,
            'credit_application_id'          => $application->id,
            'credit_facility_id'             => $facility->id,
            'type'                           => 'facility_approved',
            'direction'                      => 'credit',
            'amount'                         => $amount,
            'balance_before'                 => $before,
            'balance_after'                  => (float)$user->balance,
            'description'                    => 'تمت إضافة رصيد إلى المحفظة',
        ]);

        return $facility;
    }
}
