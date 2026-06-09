<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerServiceLeadApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:190'],
            'national_id_number' => ['required', 'string', 'min:10', 'max:30'],
            'national_id_front_document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'national_id_back_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string'],
            'existing_credit_application_id' => ['nullable', 'integer', Rule::exists('credit_applications', 'id')],
        ];
    }
}
