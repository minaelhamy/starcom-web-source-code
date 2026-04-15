<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CreditFacilityResource extends JsonResource
{
    public function toArray($request): array
    {
        $showInstitution = Auth::check() && !Auth::user()->hasRole(Role::CUSTOMER);

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
            'institution'       => $showInstitution && $this->institution ? [
                'id'           => $this->institution->id,
                'name'         => $this->institution->name,
                'company_name' => $this->institution->financialInstitutionProfile?->company_name,
            ] : null,
        ];
    }
}
