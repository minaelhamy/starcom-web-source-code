<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditFacilitySignedContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signed_contract_documents' => ['required', 'array', 'min:1', 'max:5'],
            'signed_contract_documents.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
