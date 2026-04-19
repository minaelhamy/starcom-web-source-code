<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use App\Support\StarcomIntelligenceCalculator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CreditApplicationResource extends JsonResource
{
    public function toArray($request): array
    {
        $reviewedByMe = false;
        if (Auth::check()) {
            $reviewedByMe = $this->facilities->contains('financial_institution_user_id', Auth::id());
        }

        return [
            'id'                           => $this->id,
            'status'                       => $this->status,
            'notes'                        => $this->notes,
            'created_at'                   => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'created_date'                 => $this->created_at ? AppLibrary::date($this->created_at) : null,
            'national_id_front_document'   => $this->national_id_front_document,
            'national_id_back_document'    => $this->national_id_back_document,
            'commercial_register_documents'=> $this->commercial_register_documents,
            'national_id_document'         => $this->national_id_document,
            'commercial_register_document' => $this->commercial_register_document,
            'tax_card_document'            => $this->tax_card_document,
            'reviewed_by_me'               => $reviewedByMe,
            'user'                         => $this->user ? [
                'id'              => $this->user->id,
                'name'            => $this->user->name,
                'email'           => $this->user->email,
                'phone'           => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
                'balance'         => (float)$this->user->balance,
                'wallet_balance'  => AppLibrary::currencyAmountFormat($this->user->balance),
            ] : null,
            'starcom_intelligence'         => StarcomIntelligenceCalculator::forUser($this->user),
            'facilities'                    => CreditFacilityResource::collection($this->whenLoaded('facilities')),
            'approved_amount'               => (float)$this->facilities->where('status', 'approved')->sum('approved_amount'),
            'approved_amount_currency'      => AppLibrary::currencyAmountFormat($this->facilities->where('status', 'approved')->sum('approved_amount')),
        ];
    }
}
