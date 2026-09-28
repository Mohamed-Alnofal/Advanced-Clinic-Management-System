<?php

namespace Modules\Appointments\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinics\Models\Clinic;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Users\Models\User;

class Appointment extends Model
{
    protected $fillable = [
        'doctor_id',
        'user_id',
        'clinic_id',
        'schedule_id',
        'doctor_schedule_id',
        'booking_for',
        'is_booked',
        'appointment_date',
        'status',
        'reject_reason',
        'cancel_reason',
        'appointment_type',
        'consultation_fee',
        'payment_hold_id',
        'payment_method',
        'reminders_sent',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'reminders_sent' => 'array',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
public function patient()
{
    return $this->belongsTo(User::class, 'user_id');
}
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function schedule()
{
    return $this->belongsTo(DoctorSchedule::class);
}
public function consultation()
{
    return $this->hasOne(\Modules\Consultation\Models\Consultation::class);
}

public function walletHold()
{
    return $this->belongsTo(\Modules\Wallet\Models\WalletHold::class, 'payment_hold_id');
}

public function reschedules()
{
    return $this->hasMany(\Modules\Doctors\Models\AppointmentReschedule::class);
}

public function isVideoCall(): bool
{
    return $this->appointment_type === 'video';
}

public function scheduledStartAt(): ?Carbon
{
    $schedule = $this->schedule;
    if (! $schedule || empty($schedule->start_time)) {
        return null;
    }

    $tz = \Modules\Consultation\Services\ConsultationJoinPolicy::timezone();
    $date = $this->appointment_date
        ? Carbon::parse($this->appointment_date, $tz)->toDateString()
        : Carbon::now($tz)->toDateString();
    $clock = \Modules\Consultation\Services\ConsultationJoinPolicy::normalizeClock($schedule->start_time);

    return Carbon::parse($date.' '.$clock, $tz);
}

public function isStillUpcoming(?int $lateGraceMinutes = null): bool
{
    $start = $this->scheduledStartAt();
    if (! $start) {
        return true;
    }

    $duration = max(5, (int) ($this->schedule?->consultation_duration ?: 30));
    $grace = $lateGraceMinutes ?? 0;

    return \Modules\Consultation\Services\ConsultationJoinPolicy::now()
        ->lte($start->copy()->addMinutes($duration + $grace));
}
}