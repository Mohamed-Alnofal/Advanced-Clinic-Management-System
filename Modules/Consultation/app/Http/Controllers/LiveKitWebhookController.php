<?php

namespace Modules\Consultation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Consultation\Models\Consultation;
use Modules\Consultation\Models\VideoAudit;
use Modules\Core\Http\Controllers\Controller;

class LiveKitWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $roomName = $this->firstString([
            data_get($payload, 'room.name'),
            data_get($payload, 'roomName'),
            data_get($payload, 'room_name'),
        ]);

        $roomSid = $this->firstString([
            data_get($payload, 'room.sid'),
            data_get($payload, 'roomSid'),
            data_get($payload, 'room_sid'),
        ]);

        $recordingUrl = $this->firstString([
            data_get($payload, 'recording_url'),
            data_get($payload, 'recording.url'),
            data_get($payload, 'egress.recording_url'),
            data_get($payload, 'egress.file_results.0.url'),
            data_get($payload, 'egress.file_results.0.file_url'),
            data_get($payload, 'egressInfo.fileResults.0.url'),
            data_get($payload, 'egress_info.file_results.0.url'),
        ]);

        $consultation = $this->resolveConsultation($payload, $roomName, $roomSid);

        if (! $consultation) {
            return response()->json([
                'success' => false,
                'message' => 'Consultation not found for this LiveKit event.',
            ], 404);
        }

        $updates = [];

        if ($roomSid && ! $consultation->livekit_room_sid) {
            $updates['livekit_room_sid'] = $roomSid;
        }

        if ($recordingUrl && $consultation->recording_url !== $recordingUrl) {
            $updates['recording_url'] = $recordingUrl;
        }

        $event = strtolower((string) data_get($payload, 'event', data_get($payload, 'type', '')));
        $roomClosed = in_array($event, [
            'room_finished',
            'room_ended',
            'room.finished',
            'room.ended',
        ], true);

        if (
            $roomClosed
            && ! in_array($consultation->status, ['completed', 'cancelled'], true)
        ) {
            $updates['status'] = 'completed';
            $updates['ended_at'] = now();
        }

        if ($updates !== []) {
            $consultation->update($updates);
        }

        if ($roomClosed) {
            $appointment = $consultation->appointment;
            if ($appointment && ! in_array($appointment->status, ['completed', 'cancelled'], true)) {
                $appointment->update(['status' => 'completed']);
                try {
                    app(\Modules\Appointments\Services\AppointmentEscrowService::class)
                        ->captureIfHeld($appointment->fresh());
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        VideoAudit::create([
            'consultation_id' => $consultation->id,
            'appointment_id' => $consultation->appointment_id,
            'user_id' => null,
            'user_role' => 'system',
            'event' => (string) data_get($payload, 'event', data_get($payload, 'type', 'livekit_webhook')),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => [
                'source' => 'livekit_webhook',
                'room_name' => $roomName,
                'room_sid' => $roomSid,
                'recording_url' => $recordingUrl,
                'updated_fields' => array_keys($updates),
                'payload' => $payload,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'LiveKit webhook processed successfully.',
            'data' => [
                'consultation_id' => $consultation->id,
                'appointment_id' => $consultation->appointment_id,
                'room_name' => $consultation->room_name,
                'recording_url' => $consultation->fresh()->recording_url,
            ],
        ]);
    }

    private function resolveConsultation(array $payload, ?string $roomName, ?string $roomSid): ?Consultation
    {
        $appointmentId = data_get($payload, 'appointment_id')
            ?? data_get($payload, 'appointmentId')
            ?? data_get($payload, 'metadata.appointment_id')
            ?? data_get($payload, 'metadata.appointmentId');

        if ($appointmentId) {
            $consultation = Consultation::query()->where('appointment_id', $appointmentId)->first();

            if ($consultation) {
                return $consultation;
            }
        }

        if ($roomSid) {
            $consultation = Consultation::query()->where('livekit_room_sid', $roomSid)->first();

            if ($consultation) {
                return $consultation;
            }
        }

        if ($roomName) {
            return Consultation::query()->where('room_name', $roomName)->first();
        }

        return null;
    }

    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}