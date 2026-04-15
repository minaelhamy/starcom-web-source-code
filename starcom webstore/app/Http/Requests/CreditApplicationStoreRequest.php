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
            'notes'                        => ['nullable', 'string'],
            'national_id_document'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'commercial_register_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'tax_card_document'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
