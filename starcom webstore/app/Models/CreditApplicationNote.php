<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditApplicationNote extends Model
{
    protected $fillable = [
        'credit_application_id',
        'credit_facility_id',
        'author_user_id',
        'note',
    ];

    protected $casts = [
        'id' => 'integer',
        'credit_application_id' => 'integer',
        'credit_facility_id' => 'integer',
        'author_user_id' => 'integer',
        'note' => 'string',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreditApplication::class, 'credit_application_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(CreditFacility::class, 'credit_facility_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id')->withTrashed();
    }
}
