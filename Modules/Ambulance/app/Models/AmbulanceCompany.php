<?php

namespace Modules\Ambulance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Ambulance\Database\factories\AmbulanceCompanyFactory;
use Modules\Users\Models\User;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Location\Models\Governorate;

use Modules\Ambulance\Models\AmbulanceDriver;
class AmbulanceCompany extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
    'user_id',
    'governorate_id',
    'name',
    'license_number',
    'phone',
    'email',
    'address',
    'latitude',
    'longitude',
    'logo',
    'description',
    'work_from',
    'work_to',
    'is_24_hours',
    'status',
    'is_active',
];
    
    protected static function newFactory(): AmbulanceCompanyFactory
    {
        //return AmbulanceCompanyFactory::new();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function units()
    {
        return $this->hasMany(AmbulanceUnit::class);
    }

    public function drivers()
    {
        return $this->hasMany(AmbulanceDriver::class);
    }
}
