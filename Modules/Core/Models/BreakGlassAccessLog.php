<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patients\Models\Patients;

class BreakGlassAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'accessor_id',
        'accessor_type',
        'patient_id',
        'sos_signal_id',
        'reason',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function sosSignal(): BelongsTo
    {
        return $this->belongsTo(EmergencySosSignal::class, 'sos_signal_id');
    }
}
