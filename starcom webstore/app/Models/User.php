<?php

namespace App\Models;

use Spatie\Image\Enums\Fit;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class User extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = "users";
    protected $dates = ["deleted_at"];
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'country_code',
        'address',
        'city',
        'area',
        'latitude',
        'longitude',
        'distribution_route',
        'is_guest',
        'status',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    protected $casts = [
        'id'                => 'integer',
        'name'              => 'string',
        'email'             => 'string',
        'password'          => 'hashed',
        'username'          => 'string',
        'phone'             => 'string',
        'country_code'      => 'string',
        'address'           => 'string',
        'city'              => 'string',
        'area'              => 'string',
        'latitude'          => 'string',
        'longitude'         => 'string',
        'distribution_route'=> 'string',
        'is_guest'          => 'integer',
        'status'            => 'integer',
        'email_verified_at' => 'datetime',
    ];

    public function getImageAttribute(): string
    {
        $url = $this->getFirstMediaUrl('profile');
        if (!empty($url)) {
            return str_starts_with($url, 'http') ? $url : asset($url);
        }
        return asset('images/required/profile.png');
    }

    public function getFirstNameAttribute(): string
    {
        $name = explode(' ', $this->name, 2);
        return $name[0];
    }

    public function getLastNameAttribute(): string
    {
        $name = explode(' ', $this->name, 2);
        return !empty($name[1]) ? $name[1] : '';
    }

    public function getThumbAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('profile'))) {
            $profile = $this->getMedia('profile')->last();
            return $profile->getUrl('thumb');
        }
        return asset('images/required/profile.png');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Fill, 338, 338)->keepOriginalImageFormat()->sharpen(10);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function financialInstitutionProfile(): HasOne
    {
        return $this->hasOne(FinancialInstitutionProfile::class);
    }

    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function creditFacilities(): HasMany
    {
        return $this->hasMany(CreditFacility::class);
    }

    public function institutionCreditFacilities(): HasMany
    {
        return $this->hasMany(CreditFacility::class, 'financial_institution_user_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function cartonaCustomerProfile(): HasOne
    {
        return $this->hasOne(CartonaCustomer::class);
    }

    public function cartonaOrders(): HasMany
    {
        return $this->hasMany(CartonaOrder::class);
    }

    public function intelligenceCustomers(): HasMany
    {
        return $this->hasMany(StarcomIntelligenceCustomer::class);
    }


    public function getMyRoleAttribute()
    {
        return $this->roles->pluck('id', 'id')->first();
    }

    public function getrole(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Role::class, 'id', 'myrole');
    }
    public function returnOrders()
    {
        $this->hasMany(ReturnOrder::class, 'user_id', 'id');
    }
}
