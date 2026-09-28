<?php

namespace Modules\Consultation\Services;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Modules\Appointments\Models\Appointment;
use Modules\Consultation\Models\Consultation;

class ConsultationJoinPolicy
{
    public const EARLY_MINUTES = 15;

    public const LATE_GRACE_MINUTES = 0;

    public static function timezone(): string
    {
        return (string) config(
            'consultation.timezone',
            config('app.timezone', 'Asia/Damascus')
        );
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function denyJoinResponse(
        Appointment $appointment,
        ?Consultation $consultation = null,
        $user = null
    ): ?JsonResponse {
        $consultation ??= $appointment->consultation;

        if ($consultation && in_array($consultation->status, ['completed', 'cancelled'], true)) {
            return self::endedResponse();
        }

        if (in_array($appointment->status, ['completed', 'cancelled', 'rejected'], true)) {
            return self::endedResponse();
        }

        $window = self::slotWindow($appointment);
        if ($window === null) {
            return null;
        }

        $now = self::now();
        $opensAt = $window['start']->copy()->subMinutes(self::EARLY_MINUTES);
        $closesAt = $window['end']->copy()->addMinutes(self::LATE_GRACE_MINUTES);

        if ($now->lt($opensAt)) {
            return response()->json([
                'success' => false,
                'code' => 'consultation_too_early',
                'message' => 'You can join this video call 15 minutes before the appointment time',
            ], 422);
        }

        if ($now->gt($closesAt)) {
            return response()->json([
                'success' => false,
                'code' => 'consultation_window_ended',
                'message' => 'This video consultation time has ended',
            ], 422);
        }

        return null;
    }

    /**
     * @return array{start: Carbon, end: Carbon, duration: int}|null
     */
    public static function slotWindow(Appointment $appointment): ?array
    {
        $schedule = $appointment->schedule;
        if (! $schedule || empty($schedule->start_time)) {
            return null;
        }

        $tz = self::timezone();
        $date = $appointment->appointment_date
            ? Carbon::parse($appointment->appointment_date, $tz)->toDateString()
            : self::now()->toDateString();

        $clock = self::normalizeClock($schedule->start_time);
        $start = Carbon::parse($date.' '.$clock, $tz);
        $duration = max(5, (int) ($schedule->consultation_duration ?: 30));
        $end = $start->copy()->addMinutes($duration);

        return [
            'start' => $start,
            'end' => $end,
            'duration' => $duration,
        ];
    }

    public static function livekitTtlSeconds(Appointment $appointment): int
    {
        $window = self::slotWindow($appointment);
        if ($window === null) {
            return 45 * 60;
        }

        $remaining = self::now()->diffInSeconds(
            $window['end']->copy()->addMinutes(self::LATE_GRACE_MINUTES),
            false
        );

        return max(1, min(2 * 60 * 60, (int) $remaining));
    }

    public static function normalizeClock(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        $raw = trim((string) $time);
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $raw, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }

        return '00:00:00';
    }

    private static function endedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'consultation_ended',
            'message' => 'This video consultation has already ended',
        ], 422);
    }
}
