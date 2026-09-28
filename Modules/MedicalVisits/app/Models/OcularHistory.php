<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Database\factories\OcularHistoryFactory;
use Modules\Patients\Models\PatientFile;

class OcularHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_file_id',
        'condition',
        'diagnosed_at',
        'notes'
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
