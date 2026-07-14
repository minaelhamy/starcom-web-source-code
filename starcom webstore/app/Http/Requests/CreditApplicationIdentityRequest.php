<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditApplicationIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'national_id_number' => ['required', 'string', 'max:32'],
            'national_id_front_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_back_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'commercial_register_documents'   => ['nullable', 'array', 'max:4'],
            'commercial_register_documents.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'tax_card_documents'              => ['nullable', 'array', 'max:4'],
            'tax_card_documents.*'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'rent_contract_documents'         => ['nullable', 'array', 'max:4'],
            'rent_contract_documents.*'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'utility_bill_documents'          => ['nullable', 'array', 'max:4'],
            'utility_bill_documents.*'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'additional_documents'            => ['nullable', 'array', 'max:4'],
            'additional_documents.*'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'return_to_review' => ['nullable', 'boolean'],
        ];
    }
}
