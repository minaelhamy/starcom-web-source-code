<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentApplication = null;
        if ($this->user && $this->user->relationLoaded('creditApplications')) {
            $currentApplication = $this->user->creditApplications->sortByDesc('id')->first();
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
                'address' => $this->user->address,
                'city' => $this->user->city,
                'area' => $this->user->area,
                'distribution_route' => $this->user->distribution_route,
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
            ] : null,
            'activities' => CustomerServiceLeadActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
