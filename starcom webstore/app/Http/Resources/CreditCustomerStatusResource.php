<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CreditCustomerStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        $application = $this->relationLoaded('latestCreditApplication') ? $this->latestCreditApplication : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => trim(($this->country_code ?: '') . ' ' . ($this->phone ?: '')),
            'full_name' => $application?->full_name,
            'national_id_number' => $application?->national_id_number,
            'credit_applications_count' => $this->credit_applications_count,
            'latest_credit_application_id' => $application?->id,
            'latest_credit_application_status' => $application?->status,
            'is_credit_blacklisted' => (bool) $this->credit_blacklisted_at,
            'credit_blacklist_reason' => $this->credit_blacklist_reason,
            'credit_blacklisted_at' => $this->credit_blacklisted_at?->toDateTimeString(),
            'is_top_credit_customer' => (bool) $this->is_top_credit_customer,
            'top_credit_customer_at' => $this->top_credit_customer_at?->toDateTimeString(),
        ];
    }
}
