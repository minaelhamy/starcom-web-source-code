<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FinancialInstitutionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'country_code'          => $this->country_code,
            'status'                => $this->status,
            'company_name'          => $this->financialInstitutionProfile?->company_name,
            'contact_name'          => $this->financialInstitutionProfile?->contact_name ?: $this->name,
            'contact_phone'         => $this->financialInstitutionProfile?->contact_phone,
            'notes'                 => $this->financialInstitutionProfile?->notes,
            'approved_facilities'   => (int)($this->institution_credit_facilities_count ?? $this->institutionCreditFacilities()->where('status', 'approved')->count()),
            'active_wallet_funding' => (float)$this->institutionCreditFacilities()->where('status', 'approved')->sum('available_amount'),
        ];
    }
}
