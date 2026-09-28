<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorMonthlySchedule extends Model
{

    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'doctor_schedule_id',
        'date',
        'start_time',
        'end_time',
        'consultation_duration',
        'price',
        'status',
    ];


    protected $casts = [
        'date'=>'date'
    ];


    public function doctorSchedule()
    {
        return $this->belongsTo(
            DoctorSchedule::class
        );
    }


    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class
        );
    }
}