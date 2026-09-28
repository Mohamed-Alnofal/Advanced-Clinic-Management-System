<?php

namespace Modules\HomeCare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class HomeCareRequest extends Model
{
    protected $table = 'home_care_requests';

    protected $fillable = [
        'patient_id',
        'home_care_profile_id',
        'specialty_id',
        'patient_name',
        'requested_date',
        'requested_time',
        'latitude',
        'longitude',
        'address',
        'notes',
        'status',
        'visit_fee',
        'reject_reason',
        'cancel_reason',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'requested_time' => 'string',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'visit_fee' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(HomeCareProfile::class, 'home_care_profile_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(HomeCareSpecialty::class, 'specialty_id');
    }
}
