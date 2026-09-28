<?php

namespace Modules\Ambulance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\EmergencySosSignal;
use Modules\Users\Models\User;

class EmergencyRequest extends Model
{
    protected $table = 'emergency_requests';

    protected $fillable = [
        'user_id',
        'sos_signal_id',
        'ambulance_unit_id',
        'ambulance_company_id',
        'case_number',
        'description',
        'address',
        'latitude',
        'longitude',
        'priority',
        'severity_level',
        'suspected_condition',
        'first_aid_steps',
        'status',
        'cancel_reason',
        'requested_at',
        'escalated_at',
        'accepted_at',
        'arrived_at',
        'completed_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'address' => 'encrypted',
        'description' => 'encrypted',
        'suspected_condition' => 'encrypted',
        'cancel_reason' => 'encrypted',
        'first_aid_steps' => 'encrypted:array',
        'requested_at' => 'datetime',
        'escalated_at' => 'datetime',
        'accepted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ambulanceUnit(): BelongsTo
    {
        return $this->belongsTo(AmbulanceUnit::class);
    }

    public function ambulanceCompany(): BelongsTo
    {
        return $this->belongsTo(AmbulanceCompany::class);
    }

    public function sosSignal(): BelongsTo
    {
        return $this->belongsTo(EmergencySosSignal::class, 'sos_signal_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(EmergencyRequestOffer::class);
    }
}
