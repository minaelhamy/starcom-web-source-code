<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartonaOrderItem extends Model
{
    protected $fillable = [
        'cartona_order_id',
        'cartona_order_detail_id',
        'internal_product_id',
        'product_name',
        'amount',
        'selling_price',
        'applied_supplier_discount',
        'applied_cartona_discount',
        'comment',
        'payload',
    ];

    protected $casts = [
        'cartona_order_id' => 'integer',
        'cartona_order_detail_id' => 'integer',
        'amount' => 'decimal:6',
        'selling_price' => 'decimal:6',
        'applied_supplier_discount' => 'decimal:6',
        'applied_cartona_discount' => 'decimal:6',
        'payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CartonaOrder::class, 'cartona_order_id');
    }
}
