<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartonaOrder extends Model
{
    protected $fillable = [
        'cartona_customer_id',
        'user_id',
        'hashed_id',
        'supplier_code',
        'distribution_route_code',
        'status',
        'delivery_day',
        'estimated_delivery_day',
        'cancellation_reason',
        'return_reason',
        'total_price',
        'cartona_credit',
        'installment_cost',
        'wallet_top_up',
        'note_message',
        'payload',
        'pulled_at',
    ];

    protected $casts = [
        'cartona_customer_id' => 'integer',
        'user_id' => 'integer',
        'total_price' => 'decimal:6',
        'cartona_credit' => 'decimal:6',
        'installment_cost' => 'decimal:6',
        'wallet_top_up' => 'decimal:6',
        'delivery_day' => 'date',
        'estimated_delivery_day' => 'date',
        'pulled_at' => 'datetime',
        'payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CartonaCustomer::class, 'cartona_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartonaOrderItem::class);
    }
}
