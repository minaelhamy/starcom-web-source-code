<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditApplicationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => ['required_without:decline_reason', 'nullable', 'numeric', 'min:0.01'],
            'duration_days'   => ['required_without:decline_reason', 'nullable', 'integer', 'min:30'],
            'notes'           => ['nullable', 'string'],
            'decline_reason'  => ['required_without:approved_amount', 'nullable', 'string'],
        ];
    }
}
