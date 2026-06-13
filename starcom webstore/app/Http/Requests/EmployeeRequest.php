<?php

namespace App\Http\Requests;

use App\Enums\FinancialInstitutionUserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Role as EnumRole;
use App\Models\User;

class EmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $employeeId = $this->route('employee') instanceof User
            ? $this->route('employee')->id
            : $this->route('employee');

        return [
            'name'                  => ['required', 'string', 'max:190'],
            'email'                 => [
                'required',
                'email',
                'max:190',
                Rule::unique("users", "email")->ignore($employeeId)
            ],
            'password'              => [
                $employeeId ? 'nullable' : 'required',
                'string',
                'min:6'
            ],
            'password_confirmation' => [$employeeId ? 'nullable' : 'required', 'string', 'min:6', 'same:password'],
            'username'              => [
                'nullable',
                'max:190',
                Rule::unique("users", "username")->ignore($employeeId)
            ],
            'device_token'          => ['nullable', 'string'],
            'web_token'             => ['nullable', 'string'],
            'phone'                 => [
                'nullable',
                'string',
                'max:20',
                Rule::unique("users", "phone")->ignore($employeeId)
            ],
            'status'                => ['required', 'numeric', 'max:24'],
            'role_id'               => ['required', 'numeric'],
            'country_code'          => ['required', 'string', 'max:20'],
            'financial_institution_owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'financial_institution_role' => ['nullable', Rule::in([
                FinancialInstitutionUserRole::MANAGER,
                FinancialInstitutionUserRole::EMPLOYEE,
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int)$this->input('role_id') !== EnumRole::FINANCIAL_INSTITUTION) {
                return;
            }

            $ownerId = (int)$this->input('financial_institution_owner_user_id');
            if ($ownerId <= 0) {
                $validator->errors()->add('financial_institution_owner_user_id', 'يرجى اختيار جهة التمويل التابع لها الموظف.');
                return;
            }

            if (!in_array((string)$this->input('financial_institution_role'), [
                FinancialInstitutionUserRole::MANAGER,
                FinancialInstitutionUserRole::EMPLOYEE,
            ], true)) {
                $validator->errors()->add('financial_institution_role', 'يرجى اختيار دور الموظف داخل جهة التمويل.');
                return;
            }

            $owner = User::with('financialInstitutionProfile', 'roles')->find($ownerId);
            if (!$owner || !$owner->hasRole(EnumRole::FINANCIAL_INSTITUTION) || !$owner->financialInstitutionProfile) {
                $validator->errors()->add('financial_institution_owner_user_id', 'يرجى اختيار جهة تمويل صحيحة.');
            }
        });
    }

    public function messages(){
        return [
            'password_confirmation.same' => 'Password confirmation does not match.',
            "role_id.required" => "The role field is required."
        ];
    }
}
