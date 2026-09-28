<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'file_path',
    ];
protected $hidden = [
    'created_at',
    'updated_at',
];
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
