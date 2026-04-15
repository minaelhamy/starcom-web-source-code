<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'financial_institution_user_id',
        'credit_application_id',
        'credit_facility_id',
        'order_id',
        'type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'meta',
    ];

    protected $casts = [
        'id'                            => 'integer',
        'user_id'                       => 'integer',
        'financial_institution_user_id' => 'integer',
        'credit_application_id'         => 'integer',
        'credit_facility_id'            => 'integer',
        'order_id'                      => 'integer',
        'amount'                        => 'decimal:6',
        'balance_before'                => 'decimal:6',
        'balance_after'                 => 'decimal:6',
        'meta'                          => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_institution_user_id')->withTrashed();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreditApplication::class, 'credit_application_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'credit_facility_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
