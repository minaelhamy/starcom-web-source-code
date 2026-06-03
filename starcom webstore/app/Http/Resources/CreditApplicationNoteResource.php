<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CreditApplicationNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        $author = method_exists($this->resource, 'relationLoaded')
            ? ($this->relationLoaded('author') ? $this->author : null)
            : ($this->author ?? null);
        $institution = $author?->financialInstitutionOwner ?: $author;

        return [
            'id' => $this->id,
            'note' => $this->note,
            'created_at' => $this->created_at instanceof \Carbon\CarbonInterface ? $this->created_at->toDateTimeString() : (string) $this->created_at,
            'created_date' => $this->created_at instanceof \Carbon\CarbonInterface ? $this->created_at->format('Y-m-d H:i:s') : (string) $this->created_at,
            'author' => $author ? [
                'id' => $author->id,
                'name' => $author->name,
                'email' => $author->email,
            ] : null,
            'institution' => $institution ? [
                'id' => $institution->id,
                'name' => $institution->financialInstitutionProfile?->company_name ?: $institution->name,
            ] : null,
        ];
    }
}
