<?php

namespace Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Doctors\Models\Doctor;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Database\factories\PatientFileFactory;
use Modules\MedicalVisits\Models\Surgery;
use Modules\MedicalVisits\Models\VitalSign;
use Modules\MedicalVisits\Models\OcularHistory;
use Modules\MedicalVisits\Models\FamilyHistory;
class PatientFile extends Model
{
    protected $fillable = [
        'patient_id',
        'created_by'
    ];

    protected $hidden = [
    'created_at',
    'updated_at',
];
    public function patient()
    {
        return $this->belongsTo(Patients::class);
    }

    public function creator()
    {
        return $this->belongsTo(Doctor::class,'created_by');
    }

    public function medicalVisits()
    {
        return $this->hasMany(MedicalVisits::class);
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class);
    }

    public function surgeries()
    {
        return $this->hasMany(Surgery::class);
    }

    public function familyHistories()
    {
        return $this->hasMany(FamilyHistory::class);
    }

    public function ocularHistories()
    {
        return $this->hasMany(OcularHistory::class);
    }

        public function accessRequests()
    {
        return $this->hasMany(PatientAccessRequest::class);
    }
}