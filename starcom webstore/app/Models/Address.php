<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $table = "addresses";
    protected $fillable    = ['full_name', 'email', 'country_code', 'phone', 'country', 'address', 'user_id', 'state', 'city', 'zip_code', 'latitude', 'longitude', 'google_map_url', 'route_code', 'route_name', 'address_notes', 'source'];
    protected $casts = [
        'id'           => 'integer',
        'full_name'    => 'string',
        'email'        => 'string',
        'country_code' => 'string',
        'phone'        => 'string',
        'country'      => 'string',
        'address'      => 'string',
        'user_id'      => 'integer',
        'state'        => 'string',
        'city'         => 'string',
        'zip_code'     => 'string',
        'latitude'     => 'string',
        'longitude'    => 'string',
        'google_map_url' => 'string',
        'route_code'   => 'string',
        'route_name'   => 'string',
        'address_notes'=> 'string',
        'source'       => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
