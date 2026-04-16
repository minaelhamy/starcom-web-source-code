<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarcomIntelligenceCustomer extends Model
{
    protected $fillable = [
        'user_id',
        'cartona_customer_id',
        'source',
        'external_customer_code',
        'full_name',
        'country_code',
        'phone',
        'secondary_phone',
        'address',
        'address_notes',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'google_map_url',
        'route_code',
        'route_name',
        'supplier_code',
        'orders_count',
        'total_purchase_value',
        'average_order_value',
        'first_order_at',
        'last_order_at',
        'source_payload',
        'metrics_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'cartona_customer_id' => 'integer',
        'orders_count' => 'integer',
        'total_purchase_value' => 'decimal:6',
        'average_order_value' => 'decimal:6',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'source_payload' => 'array',
        'metrics_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function cartonaCustomer(): BelongsTo
    {
        return $this->belongsTo(CartonaCustomer::class);
    }
}
