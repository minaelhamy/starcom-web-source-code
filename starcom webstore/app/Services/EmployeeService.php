<?php

namespace App\Services;

use Exception;
use App\Enums\Ask;
use App\Models\User;
use App\Enums\FinancialInstitutionUserRole;
use App\Enums\Role as EnumRole;
use App\Services\CustomerServiceLeadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ChangeImageRequest;
use App\Http\Requests\UserChangePasswordRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\FinancialInstitutionProfile;


class EmployeeService
{
    public function __construct(private readonly CustomerServiceLeadService $customerServiceLeadService)
    {
    }

    public $user;
    public $phoneFilter = ['phone'];
    public $roleFilter = ['role_id'];
    public $userFilter = ['name', 'email', 'username', 'status', 'phone', 'financial_institution_owner_user_id', 'financial_institution_role'];
    public $blockRoles = [EnumRole::ADMIN, EnumRole::CUSTOMER];


    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return User::with('media', 'addresses', 'roles', 'financialInstitutionOwner.financialInstitutionProfile')->where(
                function ($query) use ($requests) {
                    $financialInstitutionEmployeesOnly = (int)($requests['financial_institution_employee_only'] ?? 0) === 1;

                    if ($financialInstitutionEmployeesOnly) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('id', EnumRole::FINANCIAL_INSTITUTION);
                        })->whereNotNull('financial_institution_owner_user_id');
                    } else {
                        $query->whereHas('roles', function ($query) {
                            $query->where('id', '!=', EnumRole::ADMIN);
                            $query->where('id', '!=', EnumRole::CUSTOMER);
                        });
                    }

                    foreach ($requests as $key => $request) {
                        if (in_array($key, $this->roleFilter)) {
                            $query->whereHas('roles', function ($query) use ($request, $key) {
                                $query->where('id', '=', $request);
                            });
                        }
                        if (in_array($key, $this->userFilter)) {
                            if ($key == 'phone') {
                                $query->whereRaw("CONCAT(country_code, phone) LIKE ?", ["%{$request}%"]);
                            } elseif ($key === 'financial_institution_owner_user_id') {
                                $query->where($key, '=', $request);
                            } else {
                                $query->where($key, 'like', '%' . $request . '%');
                            }
                        }
                    }
                }
            )->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function store(EmployeeRequest $request)
    {
        try {
            if (!in_array($request->role_id, $this->blockRoles)) {
                DB::transaction(function () use ($request) {
                    $this->user = User::create([
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'phone'             => $request->phone,
                        'username'          => $this->username($request->email),
                        'password'          => bcrypt($request->password),
                        'status'            => $request->status,
                        'email_verified_at' => now(),
                        'country_code'      => $request->country_code,
                        'financial_institution_owner_user_id' => $this->resolveFinancialInstitutionOwnerId($request->role_id, $request->financial_institution_owner_user_id),
                        'financial_institution_role' => $this->resolveFinancialInstitutionRole($request->role_id, $request->financial_institution_role),
                        'is_guest'          => Ask::NO,
                    ]);

                    $this->user->assignRole($request->role_id);
                });

                $this->syncCustomerServiceAutomation($this->user, (int)$request->role_id);
                return $this->user;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(EmployeeRequest $request, User $employee)
    {
        try {
            if (!in_array($request->role_id, $this->blockRoles) && !in_array(
                optional($employee->roles[0])->id,
                $this->blockRoles
            )) {
                DB::transaction(function () use ($employee, $request) {
                    $this->user               = $employee;
                    $this->user->name         = $request->name;
                    $this->user->email        = $request->email;
                    $this->user->phone        = $request->phone;
                    $this->user->status       = $request->status;
                    $this->user->country_code = $request->country_code;
                    $this->user->financial_institution_owner_user_id = $this->resolveFinancialInstitutionOwnerId($request->role_id, $request->financial_institution_owner_user_id);
                    $this->user->financial_institution_role = $this->resolveFinancialInstitutionRole($request->role_id, $request->financial_institution_role);
                    if ($request->password) {
                        $this->user->password = Hash::make($request->password);
                    }
                    $this->user->save();
                });
                $this->user->syncRoles($request->role_id);
                $this->syncCustomerServiceAutomation($this->user, (int)$request->role_id);
                return $this->user;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(User $employee): User
    {
        try {
            if (!in_array(optional($employee->roles[0])->id, $this->blockRoles)) {
                return $employee;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */

    public function destroy(User $employee)
    {
        try {
            if (!in_array(optional($employee->roles[0])->id, $this->blockRoles)) {
                if ($employee->hasRole(optional($employee->roles[0])->id)) {
                    DB::transaction(function () use ($employee) {
                        $employee->addresses()->delete();
                        $employee->delete();
                    });
                } else {
                    throw new Exception(trans('all.message.permission_denied'), 422);
                }
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function username($email): string
    {
        $emails = explode('@', $email);
        return $emails[0] . mt_rand();
    }

    private function resolveFinancialInstitutionOwnerId(int $roleId, mixed $ownerId): ?int
    {
        if ($roleId !== EnumRole::FINANCIAL_INSTITUTION) {
            return null;
        }

        $resolvedOwnerId = (int)$ownerId;
        if ($resolvedOwnerId <= 0) {
            throw new Exception('يرجى اختيار جهة التمويل التابع لها الموظف.', 422);
        }

        $owner = User::with('roles', 'financialInstitutionProfile')->find($resolvedOwnerId);
        if (!$owner || !$owner->hasRole(EnumRole::FINANCIAL_INSTITUTION) || !$owner->financialInstitutionProfile instanceof FinancialInstitutionProfile) {
            throw new Exception('يرجى اختيار جهة تمويل صحيحة.', 422);
        }

        return $owner->id;
    }

    private function resolveFinancialInstitutionRole(int $roleId, mixed $financialInstitutionRole): ?string
    {
        if ($roleId !== EnumRole::FINANCIAL_INSTITUTION) {
            return null;
        }

        $resolvedRole = (string)$financialInstitutionRole;
        if (!in_array($resolvedRole, [
            FinancialInstitutionUserRole::MANAGER,
            FinancialInstitutionUserRole::EMPLOYEE,
            FinancialInstitutionUserRole::LIMITED_EMPLOYEE,
        ], true)) {
            throw new Exception('يرجى اختيار دور الموظف داخل جهة التمويل.', 422);
        }

        return $resolvedRole;
    }

    private function syncCustomerServiceAutomation(User $user, int $roleId): void
    {
        if ($roleId === EnumRole::CUSTOMER_SERVICE && (int)$user->status === 5) {
            $this->customerServiceLeadService->assignFreshLeadsToAgent($user);
            return;
        }

        if ($user->hasRole(EnumRole::CUSTOMER_SERVICE) || $roleId !== EnumRole::CUSTOMER_SERVICE || (int)$user->status !== 5) {
            $this->customerServiceLeadService->releaseAgentLeads($user);
        }
    }

    /**
     * @throws Exception
     */
    public function changePassword(UserChangePasswordRequest $request, User $employee): User
    {
        try {
            if (!in_array(optional($employee->roles[0])->id, $this->blockRoles)) {
                $employee->password = Hash::make($request->password);
                $employee->save();
                return $employee;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changeImage(ChangeImageRequest $request, User $employee): User
    {
        try {
            if (!in_array(optional($employee->roles[0])->id, $this->blockRoles)) {
                if ($request->image) {
                    $employee->clearMediaCollection('profile');
                    $employee->addMediaFromRequest('image')->toMediaCollection('profile');
                }
                return $employee;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
