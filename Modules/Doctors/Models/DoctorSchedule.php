<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Appointments\Models\Appointment;

class DoctorSchedule extends Model
{
    use HasFactory;

   protected $fillable = [
    'doctor_id',
    'clinic_id',
    'consultation_type',
    'day',
    'start_time',
    'end_time',
    'consultation_duration',
    'price',
    'is_booked',
];
protected $hidden = [
    'created_at',
    'updated_at',
];
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'schedule_id');
    }

    public function monthlySchedules()
{
    return $this->hasMany(
        \Modules\Doctors\Models\DoctorMonthlySchedule::class,
        'doctor_schedule_id'
    );
}
}
