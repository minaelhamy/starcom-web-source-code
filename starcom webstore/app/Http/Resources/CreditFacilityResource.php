<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Libraries\AppLibrary;
use App\Support\StarcomIntelligenceCalculator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CreditFacilityResource extends JsonResource
{
    public function toArray($request): array
    {
        $showInstitution = Auth::check() && !Auth::user()->hasRole(Role::CUSTOMER);
        $application = $this->relationLoaded('application') ? $this->application : null;

        return [
            'id'                => $this->id,
            'status'            => $this->status,
            'approved_amount'   => (float)$this->approved_amount,
            'available_amount'  => (float)$this->available_amount,
            'utilized_amount'   => (float)$this->utilized_amount,
            'user'              => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
                'phone' => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
            ] : null,
            'approved_currency' => AppLibrary::currencyAmountFormat($this->approved_amount),
            'available_currency'=> AppLibrary::currencyAmountFormat($this->available_amount),
            'utilized_currency' => AppLibrary::currencyAmountFormat($this->utilized_amount),
            'duration_days'     => $this->duration_days,
            'starts_at'         => $this->starts_at ? $this->starts_at->toDateString() : null,
            'due_at'            => $this->due_at ? $this->due_at->toDateString() : null,
            'reviewed_at'       => $this->reviewed_at ? $this->reviewed_at->toDateTimeString() : null,
            'notes'             => $this->notes,
            'starcom_intelligence' => StarcomIntelligenceCalculator::forUser($this->user),
            'institution'       => $showInstitution && $this->institution ? [
                'id'           => $this->institution->id,
                'name'         => $this->institution->name,
                'company_name' => $this->institution->financialInstitutionProfile?->company_name,
            ] : null,
            'application'       => $application ? [
                'id'                            => $application->id,
                'status'                        => $application->status,
                'created_at'                    => $application->created_at ? $application->created_at->toDateTimeString() : null,
                'created_date'                  => $application->created_at ? AppLibrary::date($application->created_at) : null,
                'notes'                         => $application->notes,
                'national_id_front_document'    => $application->national_id_front_document,
                'national_id_back_document'     => $application->national_id_back_document,
                'commercial_register_documents' => $application->commercial_register_documents,
                'tax_card_document'             => $application->tax_card_document,
            ] : null,
        ];
    }

}
