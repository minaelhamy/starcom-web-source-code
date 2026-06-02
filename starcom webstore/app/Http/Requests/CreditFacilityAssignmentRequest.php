<?php

namespace App\Http\Requests;

use App\Enums\Role as EnumRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditFacilityAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'financial_institution_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'financial_institution_employee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $institutionId = (int)$this->input('financial_institution_user_id');
            $employeeId = (int)$this->input('financial_institution_employee_user_id');

            $institution = $institutionId > 0 ? \App\Models\User::with('roles', 'financialInstitutionProfile')->find($institutionId) : null;
            if (!$institution || !$institution->hasRole(EnumRole::FINANCIAL_INSTITUTION) || !$institution->financialInstitutionProfile) {
                $validator->errors()->add('financial_institution_user_id', 'يرجى اختيار جهة تمويل صحيحة.');
            }

            if ($employeeId > 0) {
                $employee = \App\Models\User::with('roles')->find($employeeId);
                if (!$employee || !$employee->hasRole(EnumRole::FINANCIAL_INSTITUTION)) {
                    $validator->errors()->add('financial_institution_employee_user_id', 'يرجى اختيار موظف تمويل صحيح.');
                }
            }
        });
    }
}
