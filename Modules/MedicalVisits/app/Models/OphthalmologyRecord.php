<?php

namespace Modules\MedicalVisits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Database\factories\OphthalmologyRecordFactory;

class OphthalmologyRecord extends Model
{
    use HasFactory;

 protected $fillable = [
        'medical_visit_id',
        'visual_acuity_od',
        'visual_acuity_os',
        'iop_od',
        'iop_os',
        'cornea',
        'lens',
        'retina',
        'optic_disc',
        'slit_lamp_exam',
        'refraction',
    ];

    protected $casts = [
        'visual_acuity_od' => 'encrypted',
        'visual_acuity_os' => 'encrypted',
        'iop_od' => 'encrypted',
        'iop_os' => 'encrypted',
        'cornea' => 'encrypted',
        'lens' => 'encrypted',
        'retina' => 'encrypted',
        'optic_disc' => 'encrypted',
        'slit_lamp_exam' => 'encrypted',
        'refraction' => 'encrypted',
    ];

    protected $hidden = [
        'visual_acuity_od',
        'visual_acuity_os',
        'iop_od',
        'iop_os',
        'cornea',
        'lens',
        'retina',
        'optic_disc',
        'slit_lamp_exam',
        'refraction',
        'created_at',
        'updated_at',
    ];

    public function medicalVisit()
    {
        return $this->belongsTo(
            MedicalVisits::class,
            'medical_visit_id'
        );
    }
}
