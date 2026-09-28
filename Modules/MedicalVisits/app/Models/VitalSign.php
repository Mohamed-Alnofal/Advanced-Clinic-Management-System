<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Database\factories\VitalSignFactory;
use Modules\Patients\Models\PatientFile;

class VitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_file_id',
        'blood_type',
        'rh_factor',
        'height',
        'weight',
        'bmi'
    ];
protected $hidden = [
    'created_at',
    'updated_at',
];
    public function patientFile()
    {
        return $this->belongsTo(PatientFile::class);
    }
}
