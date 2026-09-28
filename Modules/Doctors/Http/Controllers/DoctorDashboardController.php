<?php

namespace Modules\Doctors\Http\Controllers;

use Carbon\Carbon;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Services\DoctorAppointmentService;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Models\DoctorSchedule;

/**
 * Single round-trip payload for the doctor home tab.
 * Avoids Flutter firing dashboard + upcoming (+ other tabs) on cold start.
 */
class DoctorDashboardController extends Controller
{
    public function __construct(
        private DoctorAppointmentService $appointments,
    ) {}

    public function __invoke()
    {
        $doctor = auth()->user()?->doctor;

        if (! $doctor) {
            return response()->json([
                'message' => 'Doctor profile not found',
            ], 403);
        }

        $doctorId = (int) $doctor->id;
        $today = Carbon::today()->toDateString();

        $counts = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->selectRaw(
                "SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = ? THEN 1 ELSE 0 END) AS completed_today,
                 SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_requests",
                [$today]
            )
            ->first();

        $completedToday = (int) ($counts->completed_today ?? 0);
        $pendingRequests = (int) ($counts->pending_requests ?? 0);

        $homeAppointments = $this->appointments->getHomeFeed($doctorId, 20);
        $nextSlotLabel = $this->resolveNextSlotLabel($doctorId, $homeAppointments);

        return response()->json([
            'success' => true,
            'data' => [
                'completed_today' => $completedToday,
                'pending_requests' => $pendingRequests,
                'next_slot_label' => $nextSlotLabel,
                'completedToday' => $completedToday,
                'pendingRequests' => $pendingRequests,
                'nextSlotLabel' => $nextSlotLabel,
                'appointments' => $homeAppointments,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $homeAppointments
     */
    private function resolveNextSlotLabel(int $doctorId, array $homeAppointments): string
    {
        foreach ($homeAppointments as $row) {
            if (($row['status'] ?? '') !== 'accepted') {
                continue;
            }
            $day = $row['day'] ?? '';
            $time = isset($row['time']) ? substr((string) $row['time'], 0, 5) : '';
            $label = trim($day.' '.$time);

            return $label !== '' ? $label : '—';
        }

        $openSlot = DoctorSchedule::query()
            ->where('doctor_id', $doctorId)
            ->where('is_booked', false)
            ->where('start_time', '>=', now()->format('H:i:s'))
            ->orderBy('start_time')
            ->first();

        if ($openSlot) {
            $day = $openSlot->day ?? '';
            $time = substr((string) $openSlot->start_time, 0, 5);
            $label = trim($day.' '.$time);

            return $label !== '' ? $label : '—';
        }

        return '—';
    }
}
