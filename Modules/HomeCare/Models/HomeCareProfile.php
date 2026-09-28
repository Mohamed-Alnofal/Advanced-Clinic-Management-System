<?php

namespace Modules\HomeCare\Models;

use App\Contracts\Subscribable;
use App\Traits\HasSubscriptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Modules\Doctors\Models\Doctor;
use Modules\Users\Models\User;

class HomeCareProfile extends Model implements Subscribable
{
    use HasSubscriptions;

    protected $table = 'home_care_profiles';

   protected $fillable = [
    'user_id',
    'doctor_id',
    'specialty_id',
    'provider_type',
    'full_name',
    'certificate_path',
    'bio',
    'experience_years',
    'visit_fee',
    'service_radius_km',

    'latitude',
    'longitude',
    'location_updated_at',

    'status',
    'notes',
    'available_days',
    'available_hours',
    'approved_at',
];

    protected $casts = [
        'visit_fee' => 'decimal:2',
        'service_radius_km' => 'float',
        'experience_years' => 'integer',
        'approved_at' => 'datetime',
        'available_days' => 'array',
    ];

    protected $appends = [
        'certificate_url',
    ];

    public function getCertificateUrlAttribute(): ?string
    {
        if (! $this->certificate_path) {
            return null;
        }

        return url(Storage::disk('public')->url($this->certificate_path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(HomeCareSpecialty::class, 'specialty_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HomeCareRequest::class, 'home_care_profile_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(HomeCareProviderAvailability::class, 'home_care_profile_id');
    }

    public function subscriptionType(): string
    {
        return 'home_care_provider';
    }

    public function getSubscriptionUserId(): int
    {
        return (int) $this->user_id;
    }
}
