<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CreditApplication extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'status'  => 'string',
        'notes'   => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(CreditFacility::class);
    }

    public function getNationalIdFrontDocumentAttribute(): ?string
    {
        return $this->getFirstMediaUrl('national_id_front_document') ?: null;
    }

    public function getNationalIdBackDocumentAttribute(): ?string
    {
        return $this->getFirstMediaUrl('national_id_back_document') ?: null;
    }

    public function getCommercialRegisterDocumentsAttribute(): array
    {
        return $this->getMedia('commercial_register_documents')->map(function ($media) {
            return $media->getUrl();
        })->values()->all();
    }

    public function getTaxCardDocumentAttribute(): ?string
    {
        return $this->getFirstMediaUrl('tax_card_document') ?: null;
    }

    public function getNationalIdDocumentAttribute(): ?string
    {
        return $this->national_id_front_document;
    }

    public function getCommercialRegisterDocumentAttribute(): ?string
    {
        return $this->commercial_register_documents[0] ?? null;
    }
}
