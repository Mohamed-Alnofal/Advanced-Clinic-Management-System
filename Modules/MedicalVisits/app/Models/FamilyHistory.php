<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Patients\Models\PatientFile;

class FamilyHistory extends Model
{
    protected $fillable = [
        'patient_file_id',
        'relative',
        'disease'
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
