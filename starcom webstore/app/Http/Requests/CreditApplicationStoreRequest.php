<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditApplicationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes'                           => ['nullable', 'string'],
            'national_id_front_document'      => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_back_document'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'commercial_register_documents'   => ['required', 'array', 'min:1', 'max:4'],
            'commercial_register_documents.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'tax_card_document'               => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
