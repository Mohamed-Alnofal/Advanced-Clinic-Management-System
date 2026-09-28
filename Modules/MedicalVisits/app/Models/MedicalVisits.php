<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Attachments\Models\Attachments;
use Modules\Doctors\Models\Doctor;
use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\MedicalVisits\Database\factories\MedicalVisitsFactory;
use Modules\Patients\Models\PatientAccessRequest;
use Modules\Patients\Models\PatientFile;
use Modules\Prescriptions\Models\Prescriptions;

class MedicalVisits extends Model
{
    protected $fillable = [
        'patient_file_id',
        'doctor_id',
        // 'appointment_id',
        'chief_complaint',
        'diagnosis',
        'icd_code',
        'notes',
        'treatment_plan',
        'speciality_data',
        'visit_date',
        'category',
        'visibility'
    ];

    protected $casts = [
        'chief_complaint' => 'encrypted',
        'diagnosis' => 'encrypted',
        'icd_code' => 'encrypted',
        'notes' => 'encrypted',
        'treatment_plan' => 'encrypted',
        'speciality_data' => 'encrypted:array',
        'visit_date' => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    // public function appointment()
    // {
    //     return $this->belongsTo(Appointment::class);
    // }


    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patientFile()
    {
        return $this->belongsTo(PatientFile::class);
    }

    public function accessRequests()
    {
        return $this->hasMany(PatientAccessRequest::class, 'medical_visit_id');
    }

    // public function prescriptions()
    // {
    //     return $this->hasMany(Prescriptions::class);
    // }

    public function attachments()
    {
        return $this->hasMany(Attachments::class , 'medical_visit_id');
    }

        public function prescriptions()
    {
        return $this->hasMany(Prescriptions::class,'medical_visit_id');
    }
    public function ophthalmologyRecord()
    {
        return $this->hasOne(
            OphthalmologyRecord::class,
            'medical_visit_id'
        );
    }

    public function laboratoryOrders()
    {
        return $this->hasMany(
            LaboratoryOrder::class,
            'medical_visit_id'
        );
    }
}
