<?php

namespace Modules\Doctors\Models;
use App\Contracts\Subscribable;
use App\Traits\HasSubscriptions;
use Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Clinics\Models\Clinic;
use Modules\Core\Models\Governorate;
use Modules\Core\Models\specializations;
use Modules\Doctors\States\InterfaceFactory\DoctorStateFactory;
use Modules\Notifications\Models\Notifications;
use Modules\Users\Models\FcmToken;
use Modules\Wallet\Models\Wallet;
use willvincent\Rateable\Rateable;
use Modules\Consultation\Models\VideoSubscription;
class Doctor extends Model implements Subscribable
{
     use HasFactory, Rateable, HasSubscriptions;
    protected $fillable = [
        'user_id',
        'specialization_id',
        'governorate_id',
        'bio',
        'experience_years',
        'price',
        'image',
        'clinic_name',
        'clinic_latitude',
        'clinic_longitude',
        'clinic_address',
        'is_verified',
        'is_active',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'clinic_latitude' => 'float',
        'clinic_longitude' => 'float',
    ];

    protected $hidden = [
        // Keep timestamps visible for admin verification queue.
    ];

    protected $appends = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialization()
    {
        return $this->belongsTo(specializations::class, 'specialization_id');
    }

    public function certificates()
    {
        return $this->hasMany(DoctorCertificate::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function clinics()
    {
        return $this->hasMany(Clinic::class);
    }

    public function doctorSchedule()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function state()
    {
        $status = $this->relationLoaded('user')
            ? $this->user?->status
            : $this->user()->value('status');

        return DoctorStateFactory::make($status ?? 'pending');
    }
    
        public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    public function fcmTokens()
{
    return $this->hasMany(FcmToken::class);
    
}
public function notifications()
{
    return $this->morphMany(Notifications::class, 'notifiable');
}
    // public function getCertificateUrlAttribute()
    // {
    //     return asset('storage/' . $this->medical_certificate);
    // }
    //         public function getImageUrlAttribute()
    // {
    //     return $this->image ? asset('storage/' . $this->image) : null;
    // }

// public function getImageUrlAttribute()
// {
//     return $this->image
//         ? Storage::url($this->image)
//         : null;
// }
//     public function getCertificateUrlAttribute()
// {
//     return $this->medical_certificate
//         ? asset('storage/' . $this->medical_certificate)
//         : null;
// }


public function videoSubscriptions()
{
    return $this->hasMany(VideoSubscription::class);
}

    public function subscriptionType(): string
    {
        return 'doctor';
    }

    public function getSubscriptionUserId(): int
    {
        return (int) $this->user_id;
    }

    /**
     * Doctors patients can discover: admin-verified and currently subscribed.
     */
    public function scopeListedForPatients($query)
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where($table.'.is_verified', true)
            ->whereHas('subscriptions', function ($q) {
                $q->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now());
            });
    }

    public function isListedForPatients(): bool
    {
        return (bool) $this->is_verified && $this->hasActiveSubscription();
    }
}