<?php

namespace Modules\HomeCare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeCareProviderAvailability extends Model
{
    protected $table = 'home_care_provider_availability';

    protected $fillable = [
        'home_care_profile_id',
        'type',
        'day_of_week',
        'start_date',
        'end_date',
        'excluded_dates',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'excluded_dates' => 'array',
        'is_available' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            HomeCareProfile::class,
            'home_care_profile_id'
        );
    }
}