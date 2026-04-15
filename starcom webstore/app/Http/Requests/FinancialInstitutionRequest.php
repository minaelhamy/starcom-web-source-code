<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name'           => ['required', 'string', 'max:190'],
            'name'                   => ['required', 'string', 'max:190'],
            'email'                  => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->route('financialInstitution')?->id)],
            'password'               => [$this->route('financialInstitution') ? 'nullable' : 'required', 'string', 'min:6'],
            'password_confirmation'  => [$this->route('financialInstitution') ? 'nullable' : 'required', 'same:password'],
            'country_code'           => ['required', 'string', 'max:20'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'contact_phone'          => ['nullable', 'string', 'max:20'],
            'status'                 => ['required', 'numeric'],
            'notes'                  => ['nullable', 'string'],
        ];
    }
}
