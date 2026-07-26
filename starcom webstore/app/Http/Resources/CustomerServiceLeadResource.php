<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentApplication = null;
        $currentFacility = null;
        if ($this->user && $this->user->relationLoaded('creditApplications')) {
            $currentApplication = $this->user->creditApplications->sortByDesc('id')->first();
            $currentFacility = $currentApplication?->facilities?->sortByDesc('id')->first();
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->status_label ?? $this->status,
            'priority_order' => (int)$this->priority_order,
            'assignment_cycle' => (int)$this->assignment_cycle,
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'last_contacted_at' => $this->last_contacted_at?->toDateTimeString(),
            'next_follow_up_at' => $this->next_follow_up_at?->toDateTimeString(),
            'prospect_full_name' => $this->prospect_full_name,
            'prospect_national_id_number' => $this->prospect_national_id_number,
            'documents_status' => $this->documents_status,
            'latest_note' => $this->latest_note,
            'last_pipeline_stage' => $this->last_pipeline_stage,
            'last_pipeline_stage_label' => \App\Services\CustomerServiceLeadService::pipelineStageLabels()[$this->last_pipeline_stage] ?? $this->last_pipeline_stage,
            'last_pipeline_stage_at' => $this->last_pipeline_stage_at?->toDateTimeString(),
            'source_sheet' => $this->source_sheet,
            'source_status' => $this->source_status,
            'imported_at' => $this->imported_at?->toDateTimeString(),
            'meta' => $this->meta ?: (object)[],
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'country_code' => $this->user->country_code,
                'phone' => trim(($this->user->country_code ?: '') . ' ' . ($this->user->phone ?: '')),
                'phone_plain' => $this->user->phone,
                'address' => $this->user->display_address,
                'city' => $this->user->display_city,
                'area' => $this->user->display_area,
                'distribution_route' => $this->user->distribution_route,
                'latitude' => $this->user->display_latitude,
                'longitude' => $this->user->display_longitude,
                'estimated_average_monthly_purchase' => $this->user->estimated_average_monthly_purchase,
            ] : null,
            'assigned_agent' => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null,
            'current_credit_application' => $currentApplication ? [
                'id' => $currentApplication->id,
                'status' => $currentApplication->status,
                'full_name' => $currentApplication->full_name,
                'national_id_number' => $currentApplication->national_id_number,
                'submitted_by_customer_service_user_id' => $currentApplication->submitted_by_customer_service_user_id,
                'submitted_by_customer_service_at' => $currentApplication->submitted_by_customer_service_at?->toDateTimeString(),
                'notes' => $currentApplication->notes,
            ] : null,
            'current_credit_facility' => $currentFacility ? [
                'id' => $currentFacility->id,
                'status' => $currentFacility->status,
                'approved_amount' => (float) $currentFacility->approved_amount,
                'starts_at' => $currentFacility->starts_at?->toDateString(),
                'due_at' => $currentFacility->due_at?->toDateString(),
                'institution_name' => $currentFacility->institution?->name,
                'employee_name' => $currentFacility->employee?->name,
                'repaid_amount' => (float) $currentFacility->repayments->sum('amount'),
                'repayments_count' => $currentFacility->repayments->count(),
                'contracts_count' => count($currentFacility->contract_documents ?? []),
                'signed_contracts_count' => count($currentFacility->signed_contract_documents ?? []),
            ] : null,
            'activities' => CustomerServiceLeadActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
