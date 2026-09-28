<?php 

namespace Modules\Doctors\Services;

use Modules\Appointments\Services\AppointmentSlotResolver;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Models\DoctorSchedule;

// use App\Models\DoctorSchedule;
use Modules\Consultation\Services\VideoNotificationService;
class DoctorScheduleService
{

protected VideoNotificationService $videoNotificationService;
protected AppointmentSlotResolver $slotResolver;

public function __construct(
    VideoNotificationService $videoNotificationService,
    AppointmentSlotResolver $slotResolver
) {
    $this->videoNotificationService = $videoNotificationService;
    $this->slotResolver = $slotResolver;
}
    // إنشاء أو تحديث كامل الجدول
    // public function storeOrUpdate(array $data, $doctor)
    // {
    //     // حذف الجدول القديم للطبيب
    //     DoctorSchedule::where('doctor_id', $doctor->id)->delete();

    //     $records = [];

    //     foreach ($data['schedules'] as $schedule) {

    //         $records[] = [
    //             'doctor_id' => $doctor->id,
    //             'day' => $schedule['day'],
    //             'start_time' => $schedule['start_time'],
    //             'end_time' => $schedule['end_time'],
    //             'consultation_duration' => $schedule['consultation_duration'],
    //             'price' => $schedule['price'],
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ];
    //     }

    //     DoctorSchedule::insert($records);

    //     return [
    //         'status' => true,
    //         'message' => 'Doctor schedule saved successfully',
    //         'count' => count($records)
    //     ];
    // }

//     public function storeOrUpdate(array $data, $doctor)
// {
//     DoctorSchedule::where('doctor_id', $doctor->id)->delete();

//     $records = [];

//     foreach ($data['schedules'] as $schedule) {

//         $start = \Carbon\Carbon::parse($schedule['start_time']);
//         $end = \Carbon\Carbon::parse($schedule['end_time']);
//         $duration = (int) $schedule['consultation_duration'];

//         while ($start->copy()->addMinutes($duration) <= $end) {

//             $slotEnd = $start->copy()->addMinutes($duration);

//             $records[] = [
//                 'doctor_id' => $doctor->id,
//                 'day' => $schedule['day'],
//                 'start_time' => $start->format('H:i:s'),
//                 'end_time' => $slotEnd->format('H:i:s'),

//                 'consultation_duration' => $duration,
//                 'price' => $schedule['price'],

//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ];

//             $start->addMinutes($duration);
//         }
//     }

//     DoctorSchedule::insert($records);

//     return [
//         'status' => true,
//         'message' => 'Doctor schedule generated successfully',
//         'count' => count($records),
        
//     ];
// }


public function storeOrUpdate(array $data, $doctor)
{
    $consultationType = $data['consultation_type'];

    // Scope clinic regenerate to the selected clinic only, so multi-clinic
    // doctors do not wipe availability published for their other facilities.
    $targetClinicId = null;
    if ($consultationType === 'clinic') {
        $targetClinicId = $data['clinic_id']
            ?? ($data['schedules'][0]['clinic_id'] ?? null);
        $targetClinicId = $targetClinicId !== null ? (int) $targetClinicId : null;
    }

    // Only replace slots for the days being generated (never wipe other weekdays).
    $targetDays = collect($data['schedules'] ?? [])
        ->pluck('day')
        ->filter()
        ->unique()
        ->values()
        ->all();

    $deleteQuery = DoctorSchedule::where('doctor_id', $doctor->id)
        ->where('consultation_type', $consultationType)
        ->where('is_booked', false);
    if (! empty($targetDays)) {
        $deleteQuery->whereIn('day', $targetDays);
    }
    if ($consultationType === 'clinic' && $targetClinicId) {
        $deleteQuery->where('clinic_id', $targetClinicId);
    }
    $deleteQuery->delete();

    // Remaining slots for this doctor (other days / clinics / types / booked).
    $conflicting = DoctorSchedule::where('doctor_id', $doctor->id)->get();

    $records = [];

    foreach ($data['schedules'] as $schedule) {

        $start = \Carbon\Carbon::parse($schedule['start_time']);
        $end = \Carbon\Carbon::parse($schedule['end_time']);
        $duration = (int) $schedule['consultation_duration'];
        $scheduleClinicId = $consultationType === 'clinic'
            ? (int) ($schedule['clinic_id'] ?? $targetClinicId)
            : null;

        while ($start->copy()->addMinutes($duration) <= $end) {

            $slotEnd = $start->copy()->addMinutes($duration);
            $day = $schedule['day'];
            $slotStart = $start->format('H:i:s');
            $slotEndTime = $slotEnd->format('H:i:s');

            foreach ($conflicting as $existing) {
                if ($existing->day !== $day) {
                    continue;
                }
                if ($slotStart < $existing->end_time && $slotEndTime > $existing->start_time) {
                    $label = $existing->consultation_type === 'video'
                        ? 'video'
                        : 'clinic';
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'schedules' => "Time {$slotStart}-{$slotEndTime} on {$day} overlaps with an existing {$label} slot.",
                    ]);
                }
            }

         $records[] = [
    'doctor_id' => $doctor->id,

    'clinic_id' => $scheduleClinicId,

    'consultation_type' => $consultationType,

    'day' => $day,

    'start_time' => $slotStart,

    'end_time' => $slotEndTime,

    'consultation_duration' => $duration,

    'price' => $schedule['price'],

    'is_booked' => false,

    'created_at' => now(),

    'updated_at' => now(),
];

            $conflicting->push((object) [
                'day' => $day,
                'start_time' => $slotStart,
                'end_time' => $slotEndTime,
                'consultation_type' => $consultationType,
            ]);

            $start->addMinutes($duration);
        }
    }

    DoctorSchedule::insert($records);
    $this->videoNotificationService
    ->notifyDoctorSubscribers($doctor, $records);

    return [
        'status' => true,

        'message' => 'Doctor schedule generated successfully',

        'count' => count($records),

        // ✅ عرض المواعيد
        'schedules' => collect($records)->map(function ($item) {

            return [
                'consultation_type' => $item['consultation_type'],

                'clinic_id' => $item['clinic_id'],

                'day' => $item['day'],

                'from' => $item['start_time'],

                'to' => $item['end_time'],

                'duration' => $item['consultation_duration'],

                'price' => $item['price'],

                'is_booked' => $item['is_booked'],
            ];
        })
    ];
}

    public function getMySchedule($doctor)
    {
        return DoctorSchedule::where('doctor_id', $doctor->id)
            ->orderBy('start_time')
            ->get()
            ->map(function ($item) {

                return [
                    'schedule_id' => $item->id,
                    'consultation_type' => $item->consultation_type,

                    'clinic_id' => $item->clinic_id, 

                    'day' => $item->day,

                    'start_time' => $item->start_time,

                    'end_time' => $item->end_time,

                    'consultation_duration' => $item->consultation_duration,

                    'price' => $item->price,

                    'is_booked' => $item->is_booked,
                ];
            });
    }
    // public function getSchedule($doctorId)
    // {
    //     return DoctorSchedule::where('doctor_id', $doctorId)
    //         ->orderBy('day')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'day' => $item->day,
    //                 'start_time' => $item->start_time,
    //                 'end_time' => $item->end_time,
    //                 'consultation_duration' => $item->consultation_duration,
    //             ];
    //         });
    // }

    public function getSchedule($doctorId, ?string $date = null)
{
    if (! Doctor::query()->whereKey($doctorId)->listedForPatients()->exists()) {
        return collect();
    }

    $day = $date ? \Carbon\Carbon::parse($date)->toDateString() : null;

    return DoctorSchedule::where('doctor_id', $doctorId)

        ->orderBy('day')

        ->orderBy('start_time')

        ->get()
        ->filter(function ($item) use ($day, $doctorId) {
            if (! $day) {
                return true;
            }
            $type = strtolower((string) ($item->consultation_type ?? 'clinic'));
            $clinicId = $item->clinic_id ? (int) $item->clinic_id : null;

            return ! DoctorHoliday::coversDate((int) $doctorId, $day, $clinicId, $type);
        })

        ->map(function ($item) use ($day) {
            $booked = $item->is_booked;
            if ($day) {
                $booked = $this->slotResolver->isBookedForDate((int) $item->id, $day);
            }

            return [

                'schedule_id' => $item->id,
                'consultation_type' => $item->consultation_type,

                'clinic_id' => $item->clinic_id, 

                'day' => $item->day,

                'start_time' => $item->start_time,

                'end_time' => $item->end_time,

                'consultation_duration' => $item->consultation_duration,

                'price' => $item->price,

                'is_booked' => $booked,
            ];
        });
}

    public function getSlotById(int $scheduleId): ?array
    {
        $item = DoctorSchedule::find($scheduleId);
        if (! $item) {
            return null;
        }

        return [
            'schedule_id' => $item->id,
            'consultation_type' => $item->consultation_type,
            'clinic_id' => $item->clinic_id,
            'day' => $item->day,
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'consultation_duration' => $item->consultation_duration,
            'price' => $item->price,
            'is_booked' => $item->is_booked,
        ];
    }



    public function deleteDoctorSchedules($doctor)
    {
        DoctorSchedule::where('doctor_id', $doctor->id)->delete();

        return [
            'status' => true,
            'message' => 'Doctor schedules deleted successfully'
        ];
    }
    
}