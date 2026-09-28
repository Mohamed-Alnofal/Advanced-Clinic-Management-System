<?php

namespace Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Doctors\Models\Doctor;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Database\factories\PatientAccessRequestFactory;

class PatientAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'patient_file_id',
        'medical_visit_id',
        'status',
    ];
//     protected $casts = [
//     'allowed_categories' => 'array',
// ];
    public function patient()
    {
        return $this->belongsTo(
            Patients::class,
            'patient_id'
        );
    }

    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }

        public function patientFile()
    {
        return $this->belongsTo(PatientFile::class);
    }

        public function visit()
    {
        return $this->belongsTo(MedicalVisits::class, 'medical_visit_id');
    }
}
