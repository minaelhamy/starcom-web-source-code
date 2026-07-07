<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditFacilityRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'paid_at'          => ['nullable', 'date'],
        ];
    }
}
