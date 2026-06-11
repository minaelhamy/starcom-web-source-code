<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditFacilityContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_documents' => ['required', 'array', 'min:1', 'max:5'],
            'contract_documents.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
