<?php

namespace Modules\Ambulance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Ambulance\Database\factories\AmbulanceUnitFactory;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Models\AmbulanceDriver;
class AmbulanceUnit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
 protected $fillable = [
    'ambulance_company_id',
    'ambulance_driver_id',
    'plate_number',
    'status',
    'latitude',
    'longitude',
    'last_location_update',
    'pairing_code',
    'pairing_expires_at',
    'device_token',
    'is_connected',
    'is_active',
];

    /**
     * Never leak pairing/device secrets in list/JSON serialization.
     * Explicit generatePairing() responses still return device_token intentionally.
     */
    protected $hidden = [
        'device_token',
        'pairing_code',
        'pairing_expires_at',
    ];

    protected static function newFactory(): AmbulanceUnitFactory
    {
        //return AmbulanceUnitFactory::new();
    }

    public function company()
{
    return $this->belongsTo(AmbulanceCompany::class, 'ambulance_company_id');
}

public function emergencyRequests()
{
    return $this->hasMany(EmergencyRequest::class);
}
public function driver()
{
    return $this->belongsTo(AmbulanceDriver::class, 'ambulance_driver_id');
}
}
