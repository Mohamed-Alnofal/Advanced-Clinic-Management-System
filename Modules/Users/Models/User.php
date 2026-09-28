<?php

namespace Modules\Users\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Modules\Doctors\Models\Doctor;
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\States\InterfaceFactory\DoctorStateFactory;
use Modules\Order\Models\Order;
use Modules\Patients\Models\Patients;
use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateFactory;
use Modules\Wallet\Models\Wallet;
use Modules\Consultation\Models\VideoSubscription;
// use Modules\Relatives\Moشdels\Relatives;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\MedicalRecords\Models\MedicalRecord;
use Modules\HomeCare\Models\HomeCareProfile;
use Carbon\Carbon;
class User extends Authenticatable  implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable ,   HasRoles;
protected $guard_name = 'api';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'name', 'email', 'password', 'phone', 'date_of_birth', 'gender', 
    'is_verified', 'otp', 'otp_expires_at' ,'suspension_reason','status' 
];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        // 'phone' => 'encrypted',
        // 'address' => 'encrypted',
       'date_of_birth' => 'date',
        // 'suspension_reason' => 'encrypted',
    ];
    protected $appends = [
        'age',
    ];

// public function getJWTIdentifier()
//     {
//         return $this->getKey();
//     }

//     public function getJWTCustomClaims()
//     {
//         return [];
//     }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function pharmacist()
    {
        return $this->hasOne(Pharmacist::class);
    }

    public function homeCareProfile()
    {
        return $this->hasOne(HomeCareProfile::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function relatives()
    {
        return $this->hasMany(Relatives::class);
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

        public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // public function notifications()
    // {
    //     return $this->hasMany(DatabaseNotification::class);
    // }


    public function disputesCreated()
{
    return $this->hasMany(
        \Modules\Disputes\Models\Dispute::class,
        'complainant_id'
    );
}

public function disputesAgainst()
{
    return $this->hasMany(
        \Modules\Disputes\Models\Dispute::class,
        'defendant_id'
    );
}

public function handledDisputes()
{
    return $this->hasMany(
        \Modules\Disputes\Models\Dispute::class,
        'handled_by'
    );
}

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    public function patient()
    {
        return $this->morphOne(Patients::class,'patientable');
    }
    public function notifications()
    {
        return $this->morphMany(
            \Modules\Notifications\Models\Notifications::class,
            'notifiable'
        );
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }
                public function fcmTokens()
        {
            return $this->hasMany(FcmToken::class);
        }

        public function videoSubscriptions()
        {
            return $this->hasMany(VideoSubscription::class);
        }

        public function ambulanceCompany()
        {
    return $this->hasOne(
        \Modules\Ambulance\Models\AmbulanceCompany::class
    );
        }
        
        public function emergencyRequests()
{
    return $this->hasMany(EmergencyRequest::class);
}
public function medicalRecords()
{
    return $this->hasMany(MedicalRecord::class, 'patient_id');
}

/**
 * Account lifecycle state (pending / accepted / rejected / suspended).
 */
public function state()
{
    $this->loadMissing('doctor');

    if ($this->doctor) {
        return $this->doctor->state();
    }

    return \Modules\Doctors\States\InterfaceFactory\DoctorStateFactory::make($this->status ?? 'pending');
}

public function getAgeAttribute(): ?int
{
    return $this->date_of_birth?->age;
}

public function laboratory()
{
    return $this->hasOne(
        \Modules\Laboratory\Models\Laboratory::class
    );
}
}
