<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditFacilityOrderAllocation extends Model
{
    protected $fillable = [
        'credit_facility_id',
        'order_id',
        'amount',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'credit_facility_id' => 'integer',
        'order_id'           => 'integer',
        'amount'             => 'decimal:6',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'credit_facility_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
