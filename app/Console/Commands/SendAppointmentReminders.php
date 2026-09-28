<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Appointments\Models\Appointment;
use Modules\Consultation\Services\ConsultationJoinPolicy;
use Modules\Notifications\Services\NotificationService;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Remind doctors and patients 15 minutes before and at video/clinic appointment time';

    public function handle(NotificationService $notifications): int
    {
        $now = ConsultationJoinPolicy::now();
        $appointments = Appointment::with(['user', 'doctor.user', 'schedule', 'consultation'])
            ->where('status', 'accepted')
            ->whereNotNull('appointment_date')
            ->whereDate('appointment_date', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('appointment_date', '<=', $now->copy()->addDay()->toDateString())
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            $start = $appointment->scheduledStartAt();
            if (! $start) {
                continue;
            }

            $minutesUntil = $now->diffInMinutes($start, false);
            $kind = $this->kindForMinutesUntil($minutesUntil);
            if ($kind === null) {
                continue;
            }

            if ($this->alreadySent($appointment, $kind)) {
                continue;
            }

            $this->notifyParticipants($notifications, $appointment, $kind, $start);
            $this->markSent($appointment, $kind);
            $sent++;
        }

        $this->info("Reminders sent: {$sent}");

        return Command::SUCCESS;
    }

    private function kindForMinutesUntil(float $minutesUntil): ?string
    {
        if ($minutesUntil >= 10 && $minutesUntil <= 20) {
            return 't15';
        }

        if ($minutesUntil >= -5 && $minutesUntil <= 5) {
            return 't0';
        }

        return null;
    }

    private function alreadySent(Appointment $appointment, string $kind): bool
    {
        $log = $appointment->reminders_sent ?? [];

        return ! empty($log[$kind]);
    }

    private function markSent(Appointment $appointment, string $kind): void
    {
        $log = $appointment->reminders_sent ?? [];
        $log[$kind] = now()->toIso8601String();
        $appointment->forceFill(['reminders_sent' => $log])->saveQuietly();
    }

    private function notifyParticipants(
        NotificationService $notifications,
        Appointment $appointment,
        string $kind,
        $start
    ): void {
        $time = $start->format('H:i');
        $patientName = $appointment->user->name ?? 'the patient';
        $doctorName = $appointment->doctor?->user?->name ?? 'the doctor';
        $isVideo = $appointment->isVideoCall();

        if ($kind === 't15') {
            $doctorTitle = $isVideo ? 'Video call in 15 minutes' : 'Appointment in 15 minutes';
            $doctorBody = $isVideo
                ? "Open the app to join your video visit with {$patientName} at {$time}."
                : "Your clinic visit with {$patientName} starts at {$time}.";
            $patientTitle = $isVideo ? 'Video call in 15 minutes' : 'Appointment in 15 minutes';
            $patientBody = $isVideo
                ? "You can join your video visit with Dr. {$doctorName} in 15 minutes."
                : "Your clinic visit with Dr. {$doctorName} starts at {$time}.";
        } else {
            $doctorTitle = $isVideo ? 'Join your video call now' : 'Appointment starting now';
            $doctorBody = $isVideo
                ? "It is time for your video visit with {$patientName}. Tap to start the consultation."
                : "Your clinic visit with {$patientName} is starting now.";
            $patientTitle = $isVideo ? 'Join your video call now' : 'Appointment starting now';
            $patientBody = $isVideo
                ? "Dr. {$doctorName} is ready. Open the app to join the video visit."
                : "Your clinic visit with Dr. {$doctorName} is starting now.";
        }

        $payload = [
            'appointment_id' => (string) $appointment->id,
            'type' => $isVideo ? 'video_call_reminder' : 'appointment_reminder',
            'kind' => $kind,
        ];

        $notifications->sendToUser(
            $appointment->doctor?->user,
            $doctorTitle,
            $doctorBody,
            $payload,
            $payload['type']
        );
        $notifications->sendToUser(
            $appointment->user,
            $patientTitle,
            $patientBody,
            $payload,
            $payload['type']
        );
    }
}
