<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceLeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'note' => $this->note,
            'next_follow_up_at' => $this->next_follow_up_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'role_id' => $this->actor->myRole,
            ] : null,
            'meta' => $this->meta ?: (object)[],
        ];
    }
}
