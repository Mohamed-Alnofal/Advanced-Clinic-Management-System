<?php

namespace Modules\Prescriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Prescriptions\Database\factories\PrescriptionDispensingFactory;
use Modules\Users\Models\User;

class PrescriptionDispensing extends Model
{
    protected $fillable = [
        'prescription_id',
        'pharmacy_id',
        'pharmacist_id',
        'dispensed_at',
        'status',
        'archived_pdf_path',
        'pdf_hash',
    ];

    protected $casts = [
        'dispensed_at' => 'datetime',
    ];

    public function prescription()
    {
        return $this->belongsTo(
            Prescriptions::class,
            'prescription_id'
        );
    }

    public function pharmacist()
    {
        return $this->belongsTo(
            User::class,
            'pharmacist_id'
        );
    }
}
