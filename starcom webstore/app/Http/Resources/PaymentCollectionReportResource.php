<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCollectionReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'facility_id' => $this->credit_facility_id,
            'application_id' => $this->facility?->credit_application_id,
            'customer' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'full_name' => $this->facility?->application?->full_name,
                'national_id_number' => $this->facility?->application?->national_id_number,
                'phone' => trim(($this->user?->country_code ?: '') . ' ' . ($this->user?->phone ?: '')),
                'address' => $this->user?->display_address,
                'city' => $this->user?->display_city,
                'area' => $this->user?->display_area,
            ],
            'institution' => [
                'id' => $this->facility?->institution?->id,
                'name' => $this->facility?->institution?->name,
                'company_name' => $this->facility?->institution?->financialInstitutionProfile?->company_name ?: $this->facility?->institution?->name,
            ],
            'employee' => [
                'id' => $this->facility?->employee?->id,
                'name' => $this->facility?->employee?->name,
            ],
            'amount' => (float) $this->amount,
            'amount_currency' => AppLibrary::currencyAmountFormat($this->amount),
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'paid_date' => $this->paid_at ? AppLibrary::datetime($this->paid_at) : null,
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'facility_status' => $this->facility?->status,
            'facility_status_label' => match ($this->facility?->status) {
                'approved' => 'نشط',
                'settled' => 'مسدد بالكامل',
                'declined' => 'مرفوض',
                'expired' => 'منتهي',
                'pending_approval' => 'قيد التعديل',
                default => $this->facility?->status,
            },
        ];
    }
}
