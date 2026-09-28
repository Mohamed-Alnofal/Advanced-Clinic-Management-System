<?php

namespace Modules\Ambulance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyRequestOffer extends Model
{
    protected $fillable = [
        'emergency_request_id',
        'ambulance_unit_id',
        'ambulance_driver_id',
        'status',
        'offered_at',
        'responded_at',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function emergencyRequest(): BelongsTo
    {
        return $this->belongsTo(EmergencyRequest::class);
    }

    public function ambulanceUnit(): BelongsTo
    {
        return $this->belongsTo(AmbulanceUnit::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(AmbulanceDriver::class, 'ambulance_driver_id');
    }
}
