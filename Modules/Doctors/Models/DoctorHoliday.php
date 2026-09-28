<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Doctors\Database\factories\DoctorHolidayFactory;

class DoctorHoliday extends Model
{
    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'start_date',
        'end_date',
        'reason',
    ];


    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];


    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public static function coversDate(
        int $doctorId,
        string $date,
        ?int $clinicId = null,
        ?string $consultationType = null
    ): bool {
        $day = \Carbon\Carbon::parse($date)->toDateString();

        $query = static::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day);

        // Clinic holidays (clinic_id set) do not cancel video hours.
        if ($consultationType === 'video') {
            $query->whereNull('clinic_id');
        } elseif ($clinicId !== null) {
            $query->where(function ($q) use ($clinicId) {
                $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            });
        }

        return $query->exists();
    }
}
