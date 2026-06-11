<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use App\Support\StarcomIntelligenceCalculator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CreditApplicationNoteResource;

class CreditApplicationResource extends JsonResource
{
    public function toArray($request): array
    {
        $reviewedByMe = false;
        $myReviewStatus = null;
        $canViewCustomerServiceAttribution = false;
        if (Auth::check()) {
            $actor = Auth::user();
            $canViewCustomerServiceAttribution = $actor->hasRole(\App\Enums\Role::ADMIN) || $actor->hasRole(\App\Enums\Role::MANAGER);
            if ($actor->hasRole(\App\Enums\Role::FINANCIAL_INSTITUTION)) {
                $institutionId = $actor->resolvedFinancialInstitutionUserId();
                $myFacility = $this->facilities->first(function ($facility) use ($institutionId, $actor) {
                    return (int)$facility->financial_institution_user_id === (int)$institutionId ||
                        (int)$facility->financial_institution_employee_user_id === (int)$actor->id;
                });
                $reviewedByMe = (bool)$myFacility;
                $myReviewStatus = $myFacility?->status;
            }
        }

        return [
            'id'                           => $this->id,
            'full_name'                    => $this->full_name,
            'national_id_number'           => $this->national_id_number,
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
            'my_review_status'             => $myReviewStatus,
            'notes_history'                => CreditApplicationNoteResource::collection($this->whenLoaded('notesHistory')),
            'user'                         => $this->user ? [
                'id'              => $this->user->id,
                'name'            => $this->user->name,
                'email'           => $this->user->email,
                'country_code'    => $this->user->country_code,
                'phone'           => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
                'address'         => $this->user->display_address,
                'balance'         => (float)$this->user->balance,
                'wallet_balance'  => AppLibrary::currencyAmountFormat($this->user->balance),
            ] : null,
            'starcom_intelligence'         => StarcomIntelligenceCalculator::forUser($this->user),
            'facilities'                    => CreditFacilityResource::collection($this->whenLoaded('facilities')),
            'approved_amount'               => (float)$this->facilities->where('status', 'approved')->sum('approved_amount'),
            'approved_amount_currency'      => AppLibrary::currencyAmountFormat($this->facilities->where('status', 'approved')->sum('approved_amount')),
            'submitted_by_customer_service' => $this->when($canViewCustomerServiceAttribution, $this->submittedByCustomerService ? [
                'id' => $this->submittedByCustomerService->id,
                'name' => $this->submittedByCustomerService->name,
                'phone' => trim(($this->submittedByCustomerService->country_code ?: '') . ' ' . ($this->submittedByCustomerService->phone ?: '')),
            ] : null),
            'submitted_by_customer_service_at' => $this->when(
                $canViewCustomerServiceAttribution,
                $this->submitted_by_customer_service_at?->toDateTimeString()
            ),
        ];
    }
}
