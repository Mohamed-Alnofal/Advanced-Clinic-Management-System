<?php

namespace Modules\MedicalFacility\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Clinics\Models\Clinic;
use Modules\Drug\Models\Drug;
use Modules\Core\Models\Governorate;
use Modules\Doctors\Models\Doctor;
use Modules\Laboratory\Models\Laboratory;
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Pharmacy\Models\Pharmacy;
use App\Support\PublicMedia;

class MedicalFacility extends Model
{
    use HasFactory;

    protected $table = 'medical_facilities';

    protected $fillable = [
        'governorate_id',
        'status',
        'reject_reason',
        'name',
        'type',
        'latitude',
        'longitude',
        'address',
        'phone',
        'image',
        'is_verified',
        'is_active',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // المحافظة
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    // الأطباء المرتبطين بهذه المنشأة
    public function doctors()
    {
        return $this->belongsToMany(
            Doctor::class,
            'doctor_medical_facility'
        )->withTimestamps();
    }

    // الصيادلة المرتبطين بهذه المنشأة
    public function pharmacists()
    {
        return $this->belongsToMany(
            Pharmacist::class,
            'pharmacist_medical_facility'
        )->withTimestamps();
    }
    public function clinic()
    {
        return $this->hasOne(Clinic::class);
    }

    /** Doctors linked via clinics table (shared facility support). */
    public function clinics()
    {
        return $this->hasMany(Clinic::class, 'medical_facility_id');
    }
    public function pharmacist()
    {
        return $this->hasOne(Pharmacist::class);
    }
    public function getImageAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }


    public function pharmacy()
    {
        return $this->hasOne(Pharmacy::class, 'medical_facility_id');
    }
      public function lab()
    {
        return $this->hasOne(
            Laboratory::class,
            'medical_facility_id'
        );
    }
}