<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Patients\Models\Patients;

class EmergencySosSignal extends Model
{
    public const SEVERITY_CRITICAL = 'Critical';

    public const SEVERITY_URGENT = 'Urgent';

    public const SEVERITY_STABLE = 'Stable';

    public const SEVERITY_LABELS_AR = [
        self::SEVERITY_CRITICAL => 'حرج',
        self::SEVERITY_URGENT => 'عاجل',
        self::SEVERITY_STABLE => 'مستقر',
    ];

    public $timestamps = false;

    protected $table = 'emergency_sos_signals';

    protected $fillable = [
        'patient_id',
        'latitude',
        'longitude',
        'raw_transcript',
        'severity_level',
        'suspected_condition',
        'detected_category',
        'first_aid_steps',
        'warning_signs',
        'do_not',
        'ai_confidence',
        'ai_source',
        'call_emergency_now',
        'rules_score',
        'created_at',
    ];

    protected $hidden = [
        'raw_transcript',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'first_aid_steps' => 'array',
        'warning_signs' => 'array',
        'do_not' => 'array',
        'call_emergency_now' => 'boolean',
        'created_at' => 'datetime',
        'raw_transcript' => 'encrypted',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function emergencyRequest(): HasOne
    {
        return $this->hasOne(EmergencyRequest::class, 'sos_signal_id');
    }

    public function medicalSnapshot(): HasOne
    {
        return $this->hasOne(EmergencyMedicalSnapshot::class, 'sos_signal_id');
    }

    public function breakGlassLogs(): HasMany
    {
        return $this->hasMany(BreakGlassAccessLog::class, 'sos_signal_id');
    }
}
