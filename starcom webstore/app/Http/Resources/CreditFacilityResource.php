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
        $notesHistory = $this->relationLoaded('notesHistory') ? $this->notesHistory : collect();
        $repayments = $this->relationLoaded('repayments')
            ? $this->repayments
            : $this->repayments()->with(['creator.financialInstitutionOwner.financialInstitutionProfile'])->get();
        $lastUpdatedAt = $this->updated_at;
        $lastUpdateLabel = 'تحديث المحفظة التمويلية';

        if ($notesHistory->isEmpty() && !empty($this->notes)) {
            $notesHistory = collect([
                (object) [
                    'id' => 'legacy-facility-note-' . $this->id,
                    'note' => $this->notes,
                    'created_at' => $this->reviewed_at ?: $this->updated_at ?: $this->created_at,
                    'author' => $this->employee ?: $this->institution,
                ],
            ]);
        }

        if ($application?->updated_at && (!$lastUpdatedAt || $application->updated_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $application->updated_at;
            $lastUpdateLabel = 'تحديث طلب التمويل أو مستنداته';
        }

        if ($this->user?->updated_at && (!$lastUpdatedAt || $this->user->updated_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $this->user->updated_at;
            $lastUpdateLabel = 'تحديث بيانات العميل';
        }

        if ($this->user?->latestAddress?->updated_at && (!$lastUpdatedAt || $this->user->latestAddress->updated_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $this->user->latestAddress->updated_at;
            $lastUpdateLabel = 'تحديث العنوان أو الموقع';
        }

        $latestFacilityNote = $notesHistory->sortByDesc('created_at')->first();
        if ($latestFacilityNote?->created_at && (!$lastUpdatedAt || $latestFacilityNote->created_at->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $latestFacilityNote->created_at;
            $lastUpdateLabel = 'إضافة ملاحظة أو إجراء من جهة التمويل';
        }

        $latestRepayment = $repayments->sortByDesc(function ($repayment) {
            return $repayment->paid_at ?: $repayment->created_at;
        })->first();
        $latestRepaymentAt = $latestRepayment?->paid_at ?: $latestRepayment?->created_at;
        if ($latestRepaymentAt && (!$lastUpdatedAt || $latestRepaymentAt->gt($lastUpdatedAt))) {
            $lastUpdatedAt = $latestRepaymentAt;
            $lastUpdateLabel = 'تسجيل سداد جديد';
        }

        $repaidAmount = (float) $repayments->sum('amount');
        $remainingDue = max(0, (float) $this->approved_amount - $repaidAmount);

        return [
            'id'                => $this->id,
            'full_name'         => $application?->full_name,
            'national_id_number'=> $application?->national_id_number,
            'status'            => $this->status,
            'approved_amount'   => (float)$this->approved_amount,
            'available_amount'  => (float)$this->available_amount,
            'utilized_amount'   => (float)$this->utilized_amount,
            'user'              => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
                'country_code' => $this->user->country_code,
                'phone' => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
                'address' => $this->user->display_address,
                'city' => $this->user->display_city,
                'area' => $this->user->display_area,
                'latitude' => $this->user->display_latitude,
                'longitude' => $this->user->display_longitude,
            ] : null,
            'approved_currency' => AppLibrary::currencyAmountFormat($this->approved_amount),
            'available_currency'=> AppLibrary::currencyAmountFormat($this->available_amount),
            'utilized_currency' => AppLibrary::currencyAmountFormat($this->utilized_amount),
            'repaid_amount'     => $repaidAmount,
            'repaid_currency'   => AppLibrary::currencyAmountFormat($repaidAmount),
            'remaining_due_amount' => $remainingDue,
            'remaining_due_currency' => AppLibrary::currencyAmountFormat($remainingDue),
            'duration_days'     => $this->duration_days,
            'starts_at'         => $this->starts_at ? $this->starts_at->toDateString() : null,
            'due_at'            => $this->due_at ? $this->due_at->toDateString() : null,
            'reviewed_at'       => $this->reviewed_at ? $this->reviewed_at->toDateTimeString() : null,
            'updated_at'        => $lastUpdatedAt ? $lastUpdatedAt->toDateTimeString() : null,
            'updated_date'      => $lastUpdatedAt ? AppLibrary::date($lastUpdatedAt) : null,
            'last_update_label' => $lastUpdateLabel,
            'notes'             => $this->notes,
            'notes_history'     => CreditApplicationNoteResource::collection($notesHistory),
            'repayments'        => $repayments->map(function ($repayment) {
                return [
                    'id'                 => $repayment->id,
                    'amount'             => (float) $repayment->amount,
                    'amount_currency'    => AppLibrary::currencyAmountFormat($repayment->amount),
                    'payment_method'     => $repayment->payment_method,
                    'reference_number'   => $repayment->reference_number,
                    'notes'              => $repayment->notes,
                    'paid_at'            => $repayment->paid_at ? $repayment->paid_at->toDateTimeString() : null,
                    'paid_date'          => $repayment->paid_at ? AppLibrary::datetime($repayment->paid_at) : null,
                    'created_at'         => $repayment->created_at ? $repayment->created_at->toDateTimeString() : null,
                    'created_date'       => $repayment->created_at ? AppLibrary::datetime($repayment->created_at) : null,
                    'creator'            => $repayment->creator ? [
                        'id'    => $repayment->creator->id,
                        'name'  => $repayment->creator->name,
                        'email' => $repayment->creator->email,
                    ] : null,
                ];
            })->values(),
            'starcom_intelligence' => StarcomIntelligenceCalculator::forUser($this->user),
            'institution'       => $showInstitution && $this->institution ? [
                'id'           => $this->institution->id,
                'name'         => $this->institution->name,
                'company_name' => $this->institution->financialInstitutionProfile?->company_name,
            ] : null,
            'employee'          => $showInstitution && $this->employee ? [
                'id'    => $this->employee->id,
                'name'  => $this->employee->name,
                'email' => $this->employee->email,
                'phone' => trim(($this->employee->country_code ?: '') . ' ' . ($this->employee->phone ?: '')),
            ] : null,
            'application'       => $application ? [
                'id'                            => $application->id,
                'status'                        => $application->status,
                'full_name'                     => $application->full_name,
                'national_id_number'            => $application->national_id_number,
                'created_at'                    => $application->created_at ? $application->created_at->toDateTimeString() : null,
                'created_date'                  => $application->created_at ? AppLibrary::date($application->created_at) : null,
                'notes'                         => $application->notes,
                'notes_history'                 => CreditApplicationNoteResource::collection($application->relationLoaded('notesHistory') ? $application->notesHistory : collect()),
                'national_id_front_document'    => $application->national_id_front_document,
                'national_id_back_document'     => $application->national_id_back_document,
                'commercial_register_documents' => $application->commercial_register_documents,
                'tax_card_documents'            => $application->tax_card_documents,
                'rent_contract_documents'       => $application->rent_contract_documents,
                'utility_bill_documents'        => $application->utility_bill_documents,
                'additional_documents'          => $application->additional_documents,
                'tax_card_document'             => $application->tax_card_document,
                'rent_contract_document'        => $application->rent_contract_document,
                'utility_bill_document'         => $application->utility_bill_document,
            ] : null,
            'has_contract_documents' => count($this->contract_documents) > 0,
            'contract_documents_count' => count($this->contract_documents),
            'contract_documents' => $this->contract_documents,
            'has_signed_contract_documents' => count($this->signed_contract_documents) > 0,
            'signed_contract_documents_count' => count($this->signed_contract_documents),
            'signed_contract_documents' => $this->signed_contract_documents,
        ];
    }

}
