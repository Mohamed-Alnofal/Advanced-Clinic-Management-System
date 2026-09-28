<?php

namespace Modules\Ambulance\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;

class AmbulanceDriver extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'ambulance_company_id',
        'name',
        'phone',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function company()
    {
        return $this->belongsTo(AmbulanceCompany::class);
    }

    public function unit()
    {
        return $this->hasOne(AmbulanceUnit::class, 'ambulance_driver_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}