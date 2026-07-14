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
        'submitted_by_customer_service_user_id',
        'submitted_by_customer_service_at',
        'full_name',
        'national_id_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'user_id'            => 'integer',
        'submitted_by_customer_service_user_id' => 'integer',
        'submitted_by_customer_service_at' => 'datetime',
        'full_name'          => 'string',
        'national_id_number' => 'string',
        'status'             => 'string',
        'notes'              => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function submittedByCustomerService(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_customer_service_user_id')->withTrashed();
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(CreditFacility::class);
    }

    public function notesHistory(): HasMany
    {
        return $this->hasMany(CreditApplicationNote::class)->orderBy('created_at');
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

    public function getTaxCardDocumentsAttribute(): array
    {
        return $this->getMedia('tax_card_document')->map(function ($media) {
            return $media->getUrl();
        })->values()->all();
    }

    public function getTaxCardDocumentAttribute(): ?string
    {
        return $this->tax_card_documents[0] ?? null;
    }

    public function getRentContractDocumentsAttribute(): array
    {
        return $this->getMedia('rent_contract_document')->map(function ($media) {
            return $media->getUrl();
        })->values()->all();
    }

    public function getRentContractDocumentAttribute(): ?string
    {
        return $this->rent_contract_documents[0] ?? null;
    }

    public function getUtilityBillDocumentsAttribute(): array
    {
        return $this->getMedia('utility_bill_document')->map(function ($media) {
            return $media->getUrl();
        })->values()->all();
    }

    public function getAdditionalDocumentsAttribute(): array
    {
        return $this->getMedia('additional_documents')->map(function ($media) {
            return $media->getUrl();
        })->values()->all();
    }

    public function getUtilityBillDocumentAttribute(): ?string
    {
        return $this->utility_bill_documents[0] ?? null;
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
