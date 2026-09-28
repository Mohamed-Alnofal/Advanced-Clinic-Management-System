<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Facades\Log;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Notifications\Models\Notifications;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\User;

/**
 * Patient-facing SOS lifecycle alerts (same NotificationService + FCM as the rest of ACMS).
 */
class AmbulancePatientNotifier
{
    public function notify(EmergencyRequest $request, string $event, ?string $title = null, ?string $body = null): void
    {
        $user = User::find($request->user_id);
        if (! $user) {
            return;
        }

        $type = "ambulance.{$event}";
        $requestId = (string) $request->id;

        if ($this->alreadyNotified($user, $type, $requestId)) {
            Log::info('Ambulance notify skipped (duplicate)', [
                'request_id' => $request->id,
                'event' => $event,
            ]);

            return;
        }

        [$defaultTitle, $defaultBody] = $this->copyFor($event, $request);

        $payload = [
            'type' => $type,
            'event' => $event,
            'emergency_request_id' => $requestId,
            'case_number' => (string) ($request->case_number ?? ''),
            'status' => (string) $request->status,
        ];

        try {
            app(NotificationService::class)->sendToUser(
                $user,
                $title ?? $defaultTitle,
                $body ?? $defaultBody,
                $payload,
                $type
            );
        } catch (\Throwable $e) {
            Log::warning('Ambulance patient notify failed', [
                'request_id' => $request->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function alreadyNotified(User $user, string $type, string $requestId): bool
    {
        $recent = Notifications::query()
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('created_at')
            ->limit(20)
            ->get(['data']);

        foreach ($recent as $row) {
            $data = is_array($row->data) ? $row->data : [];
            $existingId = (string) ($data['emergency_request_id'] ?? '');
            if ($existingId === $requestId) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: string, 1: string} */
    private function copyFor(string $event, EmergencyRequest $request): array
    {
        $case = $request->case_number ?? ('#'.$request->id);

        return match ($event) {
            'accepted' => [
                'Ambulance on the way',
                "A driver accepted your request {$case}. Track live ETA in the app.",
            ],
            'arrived' => [
                'Ambulance arrived',
                "Your ambulance for {$case} has arrived at your location.",
            ],
            'completed' => [
                'Mission completed',
                "Your ambulance request {$case} was completed.",
            ],
            'auto_expired' => [
                'Ambulance request closed',
                "No driver responded in time for {$case}. You can send a new request now.",
            ],
            'escalated' => [
                'Still searching for an ambulance',
                "No driver has accepted {$case} yet. Dispatch is escalating your request.",
            ],
            'cancelled' => [
                'Ambulance request cancelled',
                "Your request {$case} was cancelled.",
            ],
            default => [
                'Ambulance update',
                "Your emergency request {$case} was updated ({$event}).",
            ],
        };
    }
}
