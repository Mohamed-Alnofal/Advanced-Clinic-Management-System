<?php

namespace Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Database\factories\PatientsFactory;

class Patients extends Model
{
    protected $fillable = ['type','patientable_id','patientable_type'];
protected $hidden = [
    'created_at',
    'updated_at',
];
    public function patientable()
    {
        return $this->morphTo();
    }

    public function files()
    {
        return $this->hasMany(PatientFile::class, 'patient_id');
    }

    /** Unified chart: one file per patient (enforced by unique index). */
    public function file()
    {
        return $this->hasOne(PatientFile::class, 'patient_id');
    }

    public function visits()
    {
        return $this->hasManyThrough(
            MedicalVisits::class,
            PatientFile::class,
            'patient_id',
            'patient_file_id'
        );
    }

    
}
