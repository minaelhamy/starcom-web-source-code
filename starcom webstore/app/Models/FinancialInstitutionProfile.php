<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialInstitutionProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'contact_name',
        'contact_phone',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
