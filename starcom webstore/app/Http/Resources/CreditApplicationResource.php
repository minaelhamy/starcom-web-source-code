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
        $queueStatus = $this->status;
        $canViewCustomerServiceAttribution = false;
        $lastUpdatedAt = $this->updated_at;
        $lastUpdateLabel = 'تحديث طلب التمويل أو المستندات';
        $myFacility = null;
        $visibleFacilities = $this->relationLoaded('facilities') ? $this->facilities : collect();
        $visibleNotesHistory = $this->relationLoaded('notesHistory') ? $this->notesHistory : collect();
        $walletBalanceAmount = (float) ($this->user?->balance ?? 0);

        if ($this->user?->updated_at && (!$lastUpdatedAt || $this->user->updated_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $this->user->updated_at;
            $lastUpdateLabel = 'تحديث بيانات العميل';
        }

        if ($this->user?->latestAddress?->updated_at && (!$lastUpdatedAt || $this->user->latestAddress->updated_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $this->user->latestAddress->updated_at;
            $lastUpdateLabel = 'تحديث العنوان أو الموقع';
        }

        $latestNote = $this->relationLoaded('notesHistory') ? $this->notesHistory->sortByDesc('created_at')->first() : null;
        if ($latestNote?->created_at && (!$lastUpdatedAt || $latestNote->created_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $latestNote->created_at;
            $lastUpdateLabel = 'إضافة ملاحظة أو قرار مراجعة';
        }

        if (Auth::check()) {
            $actor = Auth::user();
            $canViewCustomerServiceAttribution = $actor->hasRole(\App\Enums\Role::ADMIN) || $actor->hasRole(\App\Enums\Role::MANAGER);
            if ($actor->hasRole(\App\Enums\Role::FINANCIAL_INSTITUTION)) {
                $institutionId = $actor->resolvedFinancialInstitutionUserId();
                $myFacility = $this->facilities
                    ->sortByDesc('id')
                    ->first(function ($facility) use ($institutionId, $actor) {
                        return (int)$facility->financial_institution_user_id === (int)$institutionId ||
                            (int)$facility->financial_institution_employee_user_id === (int)$actor->id;
                    });
                $reviewedByMe = (bool)$myFacility;
                $myReviewStatus = $myFacility?->status;
                $queueStatus = $myReviewStatus ?: \App\Enums\CreditApplicationStatus::PENDING;

                if (in_array($myReviewStatus, ['settled', 'expired'], true)) {
                    $queueStatus = \App\Enums\CreditApplicationStatus::PENDING;
                    $reviewedByMe = false;
                    $myReviewStatus = null;
                }

                $visibleFacilities = $myFacility ? collect([$myFacility]) : collect();
                $visibleNotesHistory = $visibleNotesHistory->filter(function ($note) use ($myFacility) {
                    if (!$myFacility) {
                        return !$note->credit_facility_id;
                    }

                    return !$note->credit_facility_id || (int) $note->credit_facility_id === (int) $myFacility->id;
                })->values();
                $walletBalanceAmount = $myFacility && $myFacility->status === 'approved'
                    ? (float) $myFacility->available_amount
                    : 0.0;
            }
        }

        return [
            'id'                           => $this->id,
            'full_name'                    => $this->full_name,
            'national_id_number'           => $this->national_id_number,
            'status'                       => $this->status,
            'queue_status'                 => $queueStatus,
            'notes'                        => $this->notes,
            'created_at'                   => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'created_date'                 => $this->created_at ? AppLibrary::date($this->created_at) : null,
            'updated_at'                   => $lastUpdatedAt ? $lastUpdatedAt->toDateTimeString() : null,
            'updated_date'                 => $lastUpdatedAt ? AppLibrary::date($lastUpdatedAt) : null,
            'last_update_label'            => $lastUpdateLabel,
            'national_id_front_document'   => $this->national_id_front_document,
            'national_id_back_document'    => $this->national_id_back_document,
            'commercial_register_documents'=> $this->commercial_register_documents,
            'tax_card_documents'           => $this->tax_card_documents,
            'rent_contract_documents'      => $this->rent_contract_documents,
            'utility_bill_documents'       => $this->utility_bill_documents,
            'additional_documents'         => $this->additional_documents,
            'national_id_document'         => $this->national_id_document,
            'commercial_register_document' => $this->commercial_register_document,
            'tax_card_document'            => $this->tax_card_document,
            'rent_contract_document'       => $this->rent_contract_document,
            'utility_bill_document'        => $this->utility_bill_document,
            'reviewed_by_me'               => $reviewedByMe,
            'my_review_status'             => $myReviewStatus,
            'notes_history'                => CreditApplicationNoteResource::collection($visibleNotesHistory),
            'user'                         => $this->user ? [
                'id'              => $this->user->id,
                'name'            => $this->user->name,
                'email'           => $this->user->email,
                'country_code'    => $this->user->country_code,
                'phone'           => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
                'address'         => $this->user->display_address,
                'city'            => $this->user->display_city,
                'area'            => $this->user->display_area,
                'latitude'        => $this->user->display_latitude,
                'longitude'       => $this->user->display_longitude,
                'balance'         => $walletBalanceAmount,
                'wallet_balance'  => AppLibrary::currencyAmountFormat($walletBalanceAmount),
            ] : null,
            'starcom_intelligence'         => StarcomIntelligenceCalculator::forUser($this->user),
            'facilities'                    => CreditFacilityResource::collection($visibleFacilities),
            'approved_amount'               => (float)$visibleFacilities->where('status', 'approved')->sum('approved_amount'),
            'approved_amount_currency'      => AppLibrary::currencyAmountFormat($visibleFacilities->where('status', 'approved')->sum('approved_amount')),
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
