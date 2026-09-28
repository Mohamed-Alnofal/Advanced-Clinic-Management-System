<?php

namespace Modules\Clinics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\Doctor;
use Modules\MedicalFacility\Models\MedicalFacility;

class Clinic extends Model
{
    use HasFactory;
//     protected $fillable = [
//     'medical_facility_id',
//     'doctor_id',
// ];

protected $fillable = [
    'medical_facility_id',
    'doctor_id',
];
    //  public function specialization()
    // {
    //     return $this->belongsTo(specializations::class, 'specialization_id');
    // }

    public function doctor(){
        return $this->belongsTo(Doctor::class);
    }
    public function appointments(){
        return $this->hasMany(Appointment::class);
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalFacility::class, 'medical_facility_id');
    }

}
