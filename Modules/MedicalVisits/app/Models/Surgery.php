<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Database\factories\SurgeryFactory;
use Modules\Patients\Models\PatientFile;

class Surgery extends Model
{
    use HasFactory;

  protected $fillable = [
        'patient_file_id',
        'surgery_name',
        'surgery_date',
        'notes'
    ];

        protected $casts = [
        'surgery_date' => 'date'
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
