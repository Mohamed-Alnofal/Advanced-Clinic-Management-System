<?php

namespace Modules\Prescriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Prescriptions\Database\factories\PrescriptionsFactory;

class Prescriptions extends Model
{
    use HasFactory;

      protected $fillable = [
        'medical_visit_id',
            'medical_visit_id',
    'dispensing_type',
    ];

protected $hidden = [
    'created_at',
    'updated_at',
];
    public function medicalVisit()
    {
        return $this->belongsTo(MedicalVisits::class);
    }
    public function items()
    {
        return $this->hasMany(
            PrescriptionItem::class,
            'prescription_id'
        );
    }

    public function visit()
    {
        return $this->belongsTo(
            MedicalVisits::class,
            'medical_visit_id'
        );
    }


    public function dispensings()
    {
        return $this->hasMany(
            PrescriptionDispensing::class,
            'prescription_id'
        );
    }
}
