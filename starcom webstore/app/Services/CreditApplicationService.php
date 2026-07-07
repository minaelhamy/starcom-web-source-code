<?php

namespace App\Services;

use App\Enums\CreditApplicationStatus;
use App\Enums\CreditFacilityStatus;
use App\Enums\FinancialInstitutionUserRole;
use App\Enums\Role as EnumRole;
use App\Http\Requests\CreditApplicationDecisionRequest;
use App\Http\Requests\CreditApplicationIdentityRequest;
use App\Http\Requests\CreditApplicationNoteRequest;
use App\Http\Requests\CreditFacilityAssignmentRequest;
use App\Http\Requests\CreditFacilityContractRequest;
use App\Http\Requests\CreditFacilityDatesRequest;
use App\Http\Requests\CreditFacilityRepaymentRequest;
use App\Http\Requests\CreditFacilitySignedContractRequest;
use App\Http\Requests\CreditApplicationStoreRequest;
use App\Http\Requests\CreditApplicationUpdateRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Models\CreditApplication;
use App\Models\CreditApplicationNote;
use App\Models\CreditFacility;
use App\Models\CreditFacilityRepayment;
use App\Models\User;
use App\Notifications\CreditApplicationApprovedNotification;
use App\Notifications\CreditApplicationDeclinedNotification;
use App\Notifications\NewCreditApplicationSubmittedNotification;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;
use App\Models\WalletTransaction;

class CreditApplicationService
{
    public function lenderOpportunitiesQuery(User $actor)
    {
        $this->synchronizeSettledFacilities($this->resolveInstitutionUserId($actor));
        $institutionId = $this->resolveInstitutionUserId($actor);

        return CreditApplication::with([
            'user',
            'user.latestAddress',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ])
            ->where(function ($query) use ($institutionId) {
                $query->whereDoesntHave('facilities', function ($facilityQuery) use ($institutionId) {
                    $facilityQuery->where('financial_institution_user_id', $institutionId);
                })->orWhereHas('facilities', function ($facilityQuery) use ($institutionId) {
                        $facilityQuery->where('financial_institution_user_id', $institutionId)
                        ->whereIn('status', [
                            CreditFacilityStatus::PENDING_APPROVAL,
                            CreditFacilityStatus::DECLINED,
                            CreditFacilityStatus::SETTLED,
                        ]);
                });
            });
    }

    public function lenderFreshOpportunitiesQuery(User $actor)
    {
        $this->synchronizeSettledFacilities($this->resolveInstitutionUserId($actor));
        $institutionId = $this->resolveInstitutionUserId($actor);

        return CreditApplication::with([
            'user',
            'user.latestAddress',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ])->where(function ($query) use ($institutionId) {
            $query->whereDoesntHave('facilities', function ($facilityQuery) use ($institutionId) {
                $facilityQuery->where('financial_institution_user_id', $institutionId);
            })->orWhereHas('facilities', function ($facilityQuery) use ($institutionId) {
                $facilityQuery->where('financial_institution_user_id', $institutionId)
                    ->where('status', CreditFacilityStatus::SETTLED);
            });
        });
    }

    public function lenderPendingApprovalQuery(User $actor)
    {
        $this->synchronizeSettledFacilities($this->resolveInstitutionUserId($actor));
        $institutionId = $this->resolveInstitutionUserId($actor);

        return CreditApplication::with([
            'user',
            'user.latestAddress',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ])->whereHas('facilities', function ($facilityQuery) use ($institutionId) {
            $facilityQuery->where('financial_institution_user_id', $institutionId)
                ->where('status', CreditFacilityStatus::PENDING_APPROVAL);
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
            $this->assertUserHasCoordinates(Auth::user());

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
            $this->assertUserHasCoordinates($actor);

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

            if ($request->hasFile('rent_contract_document')) {
                $creditApplication->clearMediaCollection('rent_contract_document');
                $creditApplication->addMedia($request->file('rent_contract_document'))->toMediaCollection('rent_contract_document');
            }

            if ($request->hasFile('utility_bill_document')) {
                $creditApplication->clearMediaCollection('utility_bill_document');
                $creditApplication->addMedia($request->file('utility_bill_document'))->toMediaCollection('utility_bill_document');
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

            $documentsUpdated = false;
            $creditApplication->full_name = $request->full_name;
            $creditApplication->national_id_number = $request->national_id_number;
            $creditApplication->save();

            if ($request->hasFile('national_id_front_document')) {
                $creditApplication->clearMediaCollection('national_id_front_document');
                $creditApplication->addMedia($request->file('national_id_front_document'))->toMediaCollection('national_id_front_document');
                $documentsUpdated = true;
            }

            if ($request->hasFile('national_id_back_document')) {
                $creditApplication->clearMediaCollection('national_id_back_document');
                $creditApplication->addMedia($request->file('national_id_back_document'))->toMediaCollection('national_id_back_document');
                $documentsUpdated = true;
            }

            if ($request->hasFile('commercial_register_documents')) {
                $creditApplication->clearMediaCollection('commercial_register_documents');
                foreach ($request->file('commercial_register_documents', []) as $commercialRegisterDocument) {
                    $creditApplication->addMedia($commercialRegisterDocument)->toMediaCollection('commercial_register_documents');
                }
                $documentsUpdated = true;
            }

            if ($request->hasFile('tax_card_document')) {
                $creditApplication->clearMediaCollection('tax_card_document');
                $creditApplication->addMedia($request->file('tax_card_document'))->toMediaCollection('tax_card_document');
                $documentsUpdated = true;
            }

            if ($request->hasFile('rent_contract_document')) {
                $creditApplication->clearMediaCollection('rent_contract_document');
                $creditApplication->addMedia($request->file('rent_contract_document'))->toMediaCollection('rent_contract_document');
                $documentsUpdated = true;
            }

            if ($request->hasFile('utility_bill_document')) {
                $creditApplication->clearMediaCollection('utility_bill_document');
                $creditApplication->addMedia($request->file('utility_bill_document'))->toMediaCollection('utility_bill_document');
                $documentsUpdated = true;
            }

            if (
                $request->boolean('return_to_review') &&
                $creditApplication->status === CreditApplicationStatus::PENDING_APPROVAL
            ) {
                $creditApplication->status = CreditApplicationStatus::PENDING;
                $creditApplication->save();
            }

            if ($documentsUpdated) {
                $creditApplication->touch();
                $creditApplication->facilities()->update(['updated_at' => now()]);
            }

            return $creditApplication->load([
                'user',
                'user.latestAddress',
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
            'user.latestAddress',
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

        return $query
            ->select('credit_applications.*')
            ->selectRaw("
                GREATEST(
                    UNIX_TIMESTAMP(COALESCE(credit_applications.updated_at, credit_applications.created_at)),
                    UNIX_TIMESTAMP(COALESCE((select users.updated_at from users where users.id = credit_applications.user_id limit 1), credit_applications.updated_at, credit_applications.created_at)),
                    UNIX_TIMESTAMP(COALESCE((select addresses.updated_at from addresses where addresses.user_id = credit_applications.user_id order by addresses.id desc limit 1), credit_applications.updated_at, credit_applications.created_at))
                ) as last_updated_sort
            ")
            ->orderByDesc('last_updated_sort')
            ->orderByDesc('credit_applications.id')
            ->$method($methodValue);
    }

    public function portfolioList(PaginateRequest $request)
    {
        $actor = Auth::user();
        $this->synchronizeSettledFacilities(
            $actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)
                ? $this->resolveInstitutionUserId($actor)
                : null
        );
        $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
        $term = trim((string) $request->get('term', ''));
        $hasContracts = $request->get('has_contracts');
        $hasSignedContracts = $request->get('has_signed_contracts');
        $institutionUserId = $request->get('financial_institution_user_id');
        $employeeUserId = $request->get('financial_institution_employee_user_id');

        $query = $this->portfolioQuery($actor);

        if ($actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            $query->whereIn('status', [
                CreditFacilityStatus::APPROVED,
                CreditFacilityStatus::SETTLED,
                CreditFacilityStatus::EXPIRED,
            ]);
        } else {
            if ($institutionUserId !== null && $institutionUserId !== '') {
                $query->where('financial_institution_user_id', (int) $institutionUserId);
            }

            if ($employeeUserId !== null && $employeeUserId !== '') {
                $query->where('financial_institution_employee_user_id', (int) $employeeUserId);
            }
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

        if ($hasContracts !== null && $hasContracts !== '') {
            $hasContracts = filter_var($hasContracts, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($hasContracts === true) {
                $query->whereHas('media', function ($mediaQuery) {
                    $mediaQuery->where('collection_name', 'facility_contract_documents');
                });
            } elseif ($hasContracts === false) {
                $query->whereDoesntHave('media', function ($mediaQuery) {
                    $mediaQuery->where('collection_name', 'facility_contract_documents');
                });
            }
        }

        if ($hasSignedContracts !== null && $hasSignedContracts !== '') {
            $hasSignedContracts = filter_var($hasSignedContracts, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($hasSignedContracts === true) {
                $query->whereHas('media', function ($mediaQuery) {
                    $mediaQuery->where('collection_name', 'facility_signed_contract_documents');
                });
            } elseif ($hasSignedContracts === false) {
                $query->whereDoesntHave('media', function ($mediaQuery) {
                    $mediaQuery->where('collection_name', 'facility_signed_contract_documents');
                });
            }
        }

        return $query
            ->select('credit_facilities.*')
            ->selectRaw("
                GREATEST(
                    UNIX_TIMESTAMP(COALESCE(credit_facilities.updated_at, credit_facilities.created_at)),
                    UNIX_TIMESTAMP(COALESCE((select credit_applications.updated_at from credit_applications where credit_applications.id = credit_facilities.credit_application_id limit 1), credit_facilities.updated_at, credit_facilities.created_at)),
                    UNIX_TIMESTAMP(COALESCE((select users.updated_at from users where users.id = credit_facilities.user_id limit 1), credit_facilities.updated_at, credit_facilities.created_at)),
                    UNIX_TIMESTAMP(COALESCE((select addresses.updated_at from addresses where addresses.user_id = credit_facilities.user_id order by addresses.id desc limit 1), credit_facilities.updated_at, credit_facilities.created_at))
                ) as last_updated_sort
            ")
            ->orderByDesc('last_updated_sort')
            ->orderByDesc('credit_facilities.id')
            ->$method($methodValue);
    }

    public function show(CreditApplication $creditApplication): CreditApplication
    {
        $actor = Auth::user();

        return $creditApplication->load([
            'user',
            'user.latestAddress',
            'submittedByCustomerService',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'facilities.institution.financialInstitutionProfile',
            'facilities.employee',
        ]);
    }

    public function showFacility(CreditFacility $creditFacility): CreditFacility
    {
        $actor = Auth::user();
        $this->synchronizeSettledFacilities(
            $actor->hasRole(EnumRole::FINANCIAL_INSTITUTION)
                ? $this->resolveInstitutionUserId($actor)
                : null
        );

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
            'application.user.latestAddress',
            'application.notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'notesHistory.author.financialInstitutionOwner.financialInstitutionProfile',
            'application.facilities.institution.financialInstitutionProfile',
            'application.facilities.employee',
            'institution.financialInstitutionProfile',
            'employee',
            'repayments.creator.financialInstitutionOwner.financialInstitutionProfile',
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

                $facility->touch();
                optional($facility->application)->touch();

                return $facility->load([
                    'user',
                    'user.latestAddress',
                    'application.user',
                    'application.user.latestAddress',
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
                throw new Exception('يمكن رفع العقود غير الموقعة فقط بعد اعتماد التمويل.', 422);
            }

            foreach ($request->file('contract_documents', []) as $contractDocument) {
                $creditFacility->addMedia($contractDocument)->toMediaCollection('facility_contract_documents');
            }

            $creditFacility->touch();
            optional($creditFacility->application)->touch();

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.user.latestAddress',
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

    public function uploadSignedFacilityContracts(CreditFacility $creditFacility, CreditFacilitySignedContractRequest $request): CreditFacility
    {
        try {
            $actor = Auth::user();

            if (
                !$actor->hasRole(EnumRole::ADMIN) &&
                !$actor->hasRole(EnumRole::MANAGER)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditFacility->status !== CreditFacilityStatus::APPROVED) {
                throw new Exception('يمكن رفع العقود الموقعة فقط بعد اعتماد التمويل المعتمد لهذه الجهة.', 422);
            }

            foreach ($request->file('signed_contract_documents', []) as $contractDocument) {
                $creditFacility->addMedia($contractDocument)->toMediaCollection('facility_signed_contract_documents');
            }

            $creditFacility->touch();
            optional($creditFacility->application)->touch();

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.user.latestAddress',
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

    public function deleteFacilityContract(CreditFacility $creditFacility, int $mediaId): CreditFacility
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
                (
                    !$this->isFinancialInstitutionManager($actor) ||
                    (int) $creditFacility->financial_institution_user_id !== (int) $this->resolveInstitutionUserId($actor)
                )
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            $media = $creditFacility
                ->getMedia('facility_contract_documents')
                ->firstWhere('id', (int) $mediaId);

            if (!$media instanceof Media) {
                throw new Exception('العقد المطلوب غير موجود.', 422);
            }

            $media->delete();
            $creditFacility->touch();
            optional($creditFacility->application)->touch();

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.user.latestAddress',
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

    public function deleteSignedFacilityContract(CreditFacility $creditFacility, int $mediaId): CreditFacility
    {
        try {
            $actor = Auth::user();

            if (
                !$actor->hasRole(EnumRole::ADMIN) &&
                !$actor->hasRole(EnumRole::MANAGER)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            $media = $creditFacility
                ->getMedia('facility_signed_contract_documents')
                ->firstWhere('id', (int) $mediaId);

            if (!$media instanceof Media) {
                throw new Exception('العقد الموقع المطلوب غير موجود.', 422);
            }

            $media->delete();
            $creditFacility->touch();
            optional($creditFacility->application)->touch();

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.user.latestAddress',
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

    public function updateFacilityDates(CreditFacility $creditFacility, CreditFacilityDatesRequest $request): CreditFacility
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
                (
                    !$this->isFinancialInstitutionManager($actor) ||
                    (int) $creditFacility->financial_institution_user_id !== (int) $this->resolveInstitutionUserId($actor)
                )
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            if ($creditFacility->status !== CreditFacilityStatus::APPROVED) {
                throw new Exception('يمكن تعديل بداية المدة فقط للتمويل المعتمد.', 422);
            }

            $startsAt = Carbon::parse($request->starts_at)->startOfDay();

            $creditFacility->starts_at = $startsAt;
            $creditFacility->due_at = (clone $startsAt)->addDays((int) $creditFacility->duration_days);
            $creditFacility->save();

            return $creditFacility->load([
                'user',
                'user.latestAddress',
                'application.user',
                'application.user.latestAddress',
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

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingFacility && $existingFacility->status === CreditFacilityStatus::APPROVED) {
                    throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
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
                'application.user',
                'application.user.latestAddress',
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

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingFacility && $existingFacility->status === CreditFacilityStatus::APPROVED) {
                    throw new Exception(trans('all.message.credit_application_already_reviewed'), 422);
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
                'application.user',
                'application.user.latestAddress',
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

            $facility = DB::transaction(function () use ($creditApplication, $institution, $employee, $note, $request) {
                $application = CreditApplication::lockForUpdate()->findOrFail($creditApplication->id);
                $existingFacility = CreditFacility::where('credit_application_id', $application->id)
                    ->where('financial_institution_user_id', $institution->id)
                    ->lockForUpdate()
                    ->first();

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
                'application.user',
                'application.user.latestAddress',
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function portfolioQuery(User $actor)
    {
        $query = CreditFacility::with([
            'user',
            'user.latestAddress',
            'application',
            'application.user',
            'application.user.latestAddress',
            'institution.financialInstitutionProfile',
            'employee',
            'repayments.creator.financialInstitutionOwner.financialInstitutionProfile',
        ]);

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
                optional($facility->application)->touch();
            });

            return $this->showFacility($creditFacility);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function recordRepayment(CreditFacility $creditFacility, CreditFacilityRepaymentRequest $request): CreditFacility
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
                (int) $creditFacility->financial_institution_user_id !== (int) $this->resolveInstitutionUserId($actor)
            ) {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }

            DB::transaction(function () use ($creditFacility, $request, $actor) {
                $facility = CreditFacility::with(['application', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($creditFacility->id);

                if ($facility->status !== CreditFacilityStatus::APPROVED) {
                    throw new Exception('يمكن تسجيل السداد فقط على تمويل معتمد ونشط.', 422);
                }

                $repaymentAmount = round((float) $request->amount, 6);
                $repaidBefore = round((float) $facility->repayments()->sum('amount'), 6);
                $remainingDue = max(0, round((float) $facility->approved_amount - $repaidBefore, 6));

                if ($remainingDue <= 0.000001) {
                    throw new Exception('تم سداد هذا التمويل بالكامل بالفعل.', 422);
                }

                if ($repaymentAmount - $remainingDue > 0.000001) {
                    throw new Exception('قيمة السداد أكبر من المبلغ المتبقي على هذا التمويل.', 422);
                }

                $repayment = CreditFacilityRepayment::create([
                    'credit_facility_id'            => $facility->id,
                    'user_id'                       => $facility->user_id,
                    'financial_institution_user_id' => $facility->financial_institution_user_id,
                    'amount'                        => $repaymentAmount,
                    'payment_method'                => $request->payment_method,
                    'reference_number'              => $request->reference_number,
                    'notes'                         => $request->notes,
                    'paid_at'                       => $request->filled('paid_at') ? Carbon::parse($request->paid_at) : now(),
                    'created_by_user_id'            => $actor->id,
                ]);

                $currentUtilized = round((float) $facility->utilized_amount, 6);
                $facility->utilized_amount = max(0, $currentUtilized - $repaymentAmount);

                WalletTransaction::create([
                    'user_id'                       => $facility->user_id,
                    'financial_institution_user_id' => $facility->financial_institution_user_id,
                    'credit_application_id'         => $facility->credit_application_id,
                    'credit_facility_id'            => $facility->id,
                    'type'                          => 'facility_repayment',
                    'direction'                     => 'neutral',
                    'amount'                        => $repaymentAmount,
                    'balance_before'                => (float) ($facility->user?->balance ?? 0),
                    'balance_after'                 => (float) ($facility->user?->balance ?? 0),
                    'description'                   => 'تم تسجيل سداد على التمويل مقابل الحد المعتمد',
                    'meta'                          => [
                        'repayment_id' => $repayment->id,
                        'payment_method' => $request->payment_method,
                        'reference_number' => $request->reference_number,
                        'approved_amount' => (float) $facility->approved_amount,
                        'repaid_before' => $repaidBefore,
                    ],
                ]);

                $repaidAfter = round($repaidBefore + $repaymentAmount, 6);
                $remainingAfter = max(0, round((float) $facility->approved_amount - $repaidAfter, 6));

                if ($remainingAfter <= 0.000001) {
                    $facility->utilized_amount = 0;
                    $facility->available_amount = 0;
                    $facility->status = CreditFacilityStatus::SETTLED;
                    $facility->reviewed_at = now();
                }

                $facility->save();
                optional($facility->application)->touch();
                $this->refreshApplicationStatus($facility->application);
            });

            return $this->showFacility($creditFacility);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    protected function synchronizeSettledFacilities(?int $institutionUserId = null): void
    {
        $query = CreditFacility::with(['application'])
            ->withSum('repayments', 'amount')
            ->where('status', CreditFacilityStatus::APPROVED);

        if ($institutionUserId) {
            $query->where('financial_institution_user_id', $institutionUserId);
        }

        $query->get()->each(function (CreditFacility $facility) {
            $repaidAmount = round((float) ($facility->repayments_sum_amount ?? 0), 6);
            $approvedAmount = round((float) $facility->approved_amount, 6);

            if (($approvedAmount - $repaidAmount) > 0.000001) {
                return;
            }

            $facility->available_amount = 0;
            $facility->utilized_amount = 0;
            $facility->status = CreditFacilityStatus::SETTLED;
            $facility->reviewed_at = $facility->reviewed_at ?: now();
            $facility->save();

            if ($facility->application) {
                $this->refreshApplicationStatus($facility->application);
            }
        });
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
                $application->clearMediaCollection('rent_contract_document');
                $application->clearMediaCollection('utility_bill_document');
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

    protected function isFinancialInstitutionManager(User $actor): bool
    {
        return $actor->isFinancialInstitutionManager();
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

    protected function assertUserHasCoordinates(User $user): void
    {
        $user->loadMissing('latestAddress');

        $latitude = trim((string)$user->display_latitude);
        $longitude = trim((string)$user->display_longitude);

        if ($latitude === '' || $longitude === '') {
            throw new Exception('لا يمكن تقديم طلب اشتري بالآجل قبل تسجيل الموقع الجغرافي للعميل (خط العرض وخط الطول).', 422);
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
