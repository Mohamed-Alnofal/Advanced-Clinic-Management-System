<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Database\factories\AppointmentRescheduleFactory;


class AppointmentReschedule extends Model
{


protected $fillable=[

    'appointment_id',
    'old_date',
    'old_time',
    'new_date',
    'new_time',
    'status',
    'reason',
    'created_by'

];



public function appointment()
{
    return $this->belongsTo(
        Appointment::class
    );
}

public function toApiArray(): array
{
    return [
        'id' => $this->id,
        'appointment_id' => $this->appointment_id,
        'old_date' => $this->asDateString($this->old_date),
        'old_time' => $this->asTimeString($this->old_time),
        'new_date' => $this->asDateString($this->new_date),
        'new_time' => $this->asTimeString($this->new_time),
        'status' => $this->status,
        'reason' => $this->reason,
        'created_by' => $this->created_by,
        'created_at' => optional($this->created_at)?->toISOString(),
        'updated_at' => optional($this->updated_at)?->toISOString(),
    ];
}

private function asDateString(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return \Carbon\Carbon::parse($value)->toDateString();
}

private function asTimeString(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return substr((string) \Carbon\Carbon::parse($value)->format('H:i:s'), 0, 5);
}


}
