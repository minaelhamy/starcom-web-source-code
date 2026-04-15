<?php

namespace App\Services;

use App\Enums\Ask;
use App\Enums\Role as EnumRole;
use App\Http\Requests\FinancialInstitutionRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Models\FinancialInstitutionProfile;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class FinancialInstitutionService
{
    public function list(PaginateRequest $request)
    {
        try {
            $requests = $request->all();
            $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType = $request->get('order_type') ?? 'desc';

            return User::with('financialInstitutionProfile')
                ->withCount('institutionCreditFacilities')
                ->role(EnumRole::FINANCIAL_INSTITUTION)
                ->where(function ($query) use ($requests) {
                    foreach ($requests as $key => $value) {
                        if (blank($value)) {
                            continue;
                        }

                        if (in_array($key, ['name', 'email', 'phone', 'status'])) {
                            $query->where($key, 'like', '%' . $value . '%');
                        }

                        if ($key === 'company_name') {
                            $query->whereHas('financialInstitutionProfile', function ($profileQuery) use ($value) {
                                $profileQuery->where('company_name', 'like', '%' . $value . '%');
                            });
                        }
                    }
                })
                ->orderBy($orderColumn, $orderType)
                ->$method($methodValue);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function store(FinancialInstitutionRequest $request): User
    {
        try {
            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'name'              => $request->name,
                    'email'             => $request->email,
                    'phone'             => $request->phone,
                    'username'          => AppLibrary::username($request->company_name),
                    'password'          => Hash::make($request->password),
                    'status'            => $request->status,
                    'email_verified_at' => now(),
                    'country_code'      => $request->country_code,
                    'is_guest'          => Ask::NO,
                ]);
                $user->assignRole(EnumRole::FINANCIAL_INSTITUTION);

                FinancialInstitutionProfile::create([
                    'user_id'       => $user->id,
                    'company_name'  => $request->company_name,
                    'contact_name'  => $request->name,
                    'contact_phone' => $request->contact_phone ?: $request->phone,
                    'notes'         => $request->notes,
                ]);

                return $user->load('financialInstitutionProfile');
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function update(FinancialInstitutionRequest $request, User $financialInstitution): User
    {
        try {
            return DB::transaction(function () use ($request, $financialInstitution) {
                $financialInstitution->name = $request->name;
                $financialInstitution->email = $request->email;
                $financialInstitution->phone = $request->phone;
                $financialInstitution->country_code = $request->country_code;
                $financialInstitution->status = $request->status;
                if ($request->password) {
                    $financialInstitution->password = Hash::make($request->password);
                }
                $financialInstitution->save();

                $profile = $financialInstitution->financialInstitutionProfile ?: new FinancialInstitutionProfile(['user_id' => $financialInstitution->id]);
                $profile->company_name = $request->company_name;
                $profile->contact_name = $request->name;
                $profile->contact_phone = $request->contact_phone ?: $request->phone;
                $profile->notes = $request->notes;
                $profile->save();

                return $financialInstitution->load('financialInstitutionProfile');
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function show(User $financialInstitution): User
    {
        if (!$financialInstitution->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }

        return $financialInstitution->load('financialInstitutionProfile');
    }
}
