<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditFacilityRepayment extends Model
{
    protected $fillable = [
        'credit_facility_id',
        'user_id',
        'financial_institution_user_id',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'paid_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'id'                            => 'integer',
        'credit_facility_id'            => 'integer',
        'user_id'                       => 'integer',
        'financial_institution_user_id' => 'integer',
        'created_by_user_id'            => 'integer',
        'amount'                        => 'decimal:6',
        'paid_at'                       => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'credit_facility_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_institution_user_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }
}
