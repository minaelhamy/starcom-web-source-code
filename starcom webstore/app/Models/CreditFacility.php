<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CreditFacility extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'credit_application_id',
        'user_id',
        'financial_institution_user_id',
        'financial_institution_employee_user_id',
        'status',
        'approved_amount',
        'available_amount',
        'utilized_amount',
        'duration_days',
        'starts_at',
        'due_at',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'id'                            => 'integer',
        'credit_application_id'         => 'integer',
        'user_id'                       => 'integer',
        'financial_institution_user_id' => 'integer',
        'financial_institution_employee_user_id' => 'integer',
        'status'                        => 'string',
        'approved_amount'               => 'decimal:6',
        'available_amount'              => 'decimal:6',
        'utilized_amount'               => 'decimal:6',
        'duration_days'                 => 'integer',
        'starts_at'                     => 'datetime',
        'due_at'                        => 'datetime',
        'reviewed_at'                   => 'datetime',
        'notes'                         => 'string',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreditApplication::class, 'credit_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_institution_user_id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_institution_employee_user_id')->withTrashed();
    }

    public function orderAllocations(): HasMany
    {
        return $this->hasMany(CreditFacilityOrderAllocation::class);
    }

    public function notesHistory(): HasMany
    {
        return $this->hasMany(CreditApplicationNote::class)->orderBy('created_at');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(CreditFacilityRepayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function getContractDocumentsAttribute(): array
    {
        return $this->getMedia('facility_contract_documents')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
            ];
        })->values()->all();
    }

    public function getSignedContractDocumentsAttribute(): array
    {
        return $this->getMedia('facility_signed_contract_documents')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
            ];
        })->values()->all();
    }

    public function getClientProfilePictureAttribute(): ?array
    {
        $media = $this->getFirstMedia('facility_client_profile_picture');

        return $media ? [
            'id' => $media->id,
            'name' => $media->file_name,
            'url' => $media->getUrl(),
        ] : null;
    }

    public function getClientSigningPictureAttribute(): ?array
    {
        $media = $this->getFirstMedia('facility_client_signing_picture');

        return $media ? [
            'id' => $media->id,
            'name' => $media->file_name,
            'url' => $media->getUrl(),
        ] : null;
    }
}
