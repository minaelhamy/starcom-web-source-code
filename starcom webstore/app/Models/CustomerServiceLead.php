<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerServiceLead extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_to_user_id',
        'status',
        'priority_order',
        'assignment_cycle',
        'assigned_at',
        'last_contacted_at',
        'next_follow_up_at',
        'prospect_full_name',
        'prospect_national_id_number',
        'documents_status',
        'last_pipeline_stage',
        'last_pipeline_stage_at',
        'latest_note',
        'source_sheet',
        'source_status',
        'imported_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'assigned_to_user_id' => 'integer',
        'priority_order' => 'integer',
        'assignment_cycle' => 'integer',
        'assigned_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'last_pipeline_stage_at' => 'datetime',
        'imported_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerServiceLeadActivity::class)->orderByDesc('created_at');
    }
}
