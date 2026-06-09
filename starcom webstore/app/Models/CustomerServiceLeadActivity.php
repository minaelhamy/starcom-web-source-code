<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerServiceLeadActivity extends Model
{
    protected $fillable = [
        'customer_service_lead_id',
        'actor_user_id',
        'status',
        'note',
        'next_follow_up_at',
        'meta',
    ];

    protected $casts = [
        'customer_service_lead_id' => 'integer',
        'actor_user_id' => 'integer',
        'next_follow_up_at' => 'datetime',
        'meta' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CustomerServiceLead::class, 'customer_service_lead_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
