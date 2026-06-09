<?php

namespace App\Http\Requests;

use App\Enums\CustomerServiceLeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerServiceLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                CustomerServiceLeadStatus::WAITING_DOCUMENTS,
                CustomerServiceLeadStatus::DOCUMENTS_RECEIVED,
                CustomerServiceLeadStatus::NOT_INTERESTED,
                CustomerServiceLeadStatus::VISIT_REQUIRED,
                CustomerServiceLeadStatus::NO_ANSWER,
                CustomerServiceLeadStatus::CONTACTED_WAITING_REPLY,
                CustomerServiceLeadStatus::CALL_LATER,
                CustomerServiceLeadStatus::REJECTED_COMMERCIAL_REGISTER,
                CustomerServiceLeadStatus::REVIEW_WITH_OWNER,
                CustomerServiceLeadStatus::NO_CREDIT_SALES,
                CustomerServiceLeadStatus::NO_REGISTER_NO_ID_CONSENT,
                CustomerServiceLeadStatus::CLOSED,
            ])],
            'note' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}
