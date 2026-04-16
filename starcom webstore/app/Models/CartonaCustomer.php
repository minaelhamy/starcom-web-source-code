<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartonaCustomer extends Model
{
    protected $fillable = [
        'user_id',
        'retailer_code',
        'retailer_name',
        'retailer_number',
        'retailer_number2',
        'country_code',
        'phone',
        'secondary_country_code',
        'secondary_phone',
        'retailer_address',
        'address_notes',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'google_map_url',
        'distribution_route_code',
        'supplier_code',
        'latest_payload',
        'first_order_at',
        'last_order_at',
        'last_synced_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'latest_payload' => 'array',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CartonaOrder::class);
    }

    public function intelligenceProfile(): HasMany
    {
        return $this->hasMany(StarcomIntelligenceCustomer::class);
    }
}
