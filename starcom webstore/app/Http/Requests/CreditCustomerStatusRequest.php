<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blacklisted' => ['sometimes', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'max:2000'],
            'is_top_credit_customer' => ['sometimes', 'boolean'],
        ];
    }
}
