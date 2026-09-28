<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patients\Models\Patients;

class EmergencyMedicalSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sos_signal_id',
        'patient_id',
        'vitals_json',
        'allergies_json',
        'chronic_conditions_json',
        'last_visits_json',
        'created_at',
    ];

    protected $casts = [
        'vitals_json' => 'encrypted:array',
        'allergies_json' => 'encrypted:array',
        'chronic_conditions_json' => 'encrypted:array',
        'last_visits_json' => 'encrypted:array',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'vitals_json',
        'allergies_json',
        'chronic_conditions_json',
        'last_visits_json',
    ];

    public function sosSignal(): BelongsTo
    {
        return $this->belongsTo(EmergencySosSignal::class, 'sos_signal_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }
}
