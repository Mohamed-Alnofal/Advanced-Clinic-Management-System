<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Core\Models\EmergencySosSignal;

class EmergencyRequestService
{
    public function __construct(
        private AmbulanceDispatchService $dispatch,
    ) {}

    public static function escalateAfterMinutes(): int
    {
        return max(1, (int) config('ambulance.dispatch.escalate_pending_after_minutes', 2));
    }

    public static function pendingExpireMinutes(): int
    {
        return max(1, (int) config('ambulance.dispatch.expire_pending_after_minutes', 5));
    }

    public static function assignedExpireHours(): int
    {
        return max(1, (int) config('ambulance.dispatch.expire_assigned_after_hours', 6));
    }

    public static function lifecycleMeta(): array
    {
        return [
            'escalate_after_minutes' => self::escalateAfterMinutes(),
            'expire_pending_after_minutes' => self::pendingExpireMinutes(),
            'expire_assigned_after_hours' => self::assignedExpireHours(),
        ];
    }

    /**
     * Create a pending emergency request, then offer it to the nearest available driver.
     */
    public function store(int $userId, array $data, bool $offerNearest = true): array
    {
        $created = DB::transaction(function () use ($userId, $data) {
            // Unlock patients stuck on abandoned SOS / missions.
            $this->autoExpireStaleForUser($userId);

            $active = EmergencyRequest::where('user_id', $userId)
                ->whereIn('status', ['pending', 'assigned', 'arrived'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($active) {
                return [
                    'message' => 'You already have an active emergency request.',
                    'code' => 'ACTIVE_REQUEST_EXISTS',
                    'active_request' => $this->serializeActive($active->load(['ambulanceUnit.driver', 'sosSignal'])),
                ];
            }

            $signal = null;
            $priority = $data['priority'] ?? 'medium';
            $severity = $data['severity_level'] ?? null;
            $condition = $data['suspected_condition'] ?? null;
            $firstAid = $data['first_aid_steps'] ?? null;

            if (! empty($data['sos_signal_id'])) {
                $signal = EmergencySosSignal::whereKey($data['sos_signal_id'])->first();
                if (
                    ! $signal
                    || ($signal->user_id !== null && (int) $signal->user_id !== $userId)
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'sos_signal_id' => 'Invalid or unauthorized SOS signal.',
                    ]);
                }

                if ($signal->user_id === null) {
                    $signal->update(['user_id' => $userId]);
                }

                $severity = $severity ?? $signal->severity_level;
                $condition = $condition ?? $signal->suspected_condition;
                $firstAid = $firstAid ?? $signal->first_aid_steps;

                if ($signal->severity_level === EmergencySosSignal::SEVERITY_CRITICAL) {
                    $priority = 'critical';
                } elseif ($signal->severity_level === EmergencySosSignal::SEVERITY_URGENT) {
                    $priority = 'high';
                }
            }

            $request = EmergencyRequest::create([
                'user_id' => $userId,
                'sos_signal_id' => $data['sos_signal_id'] ?? null,
                'case_number' => 'SOS-'.now()->format('Ymd').'-'.random_int(1000, 9999),
                'description' => $data['description'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'priority' => $priority,
                'severity_level' => $severity,
                'suspected_condition' => $condition,
                'first_aid_steps' => $firstAid,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            return [
                'message' => 'SOS request sent successfully.',
                'request' => $request->load('sosSignal'),
            ];
        });

        if (! isset($created['request']) || ! $offerNearest) {
            return $created;
        }

        $offer = $this->dispatch->offerToNearest($created['request']);
        $request = $created['request']->fresh(['sosSignal']);

        return [
            'message' => $offer
                ? 'SOS request sent. Nearest ambulance driver notified.'
                : 'SOS request sent. No available ambulance nearby yet — keep the app open; we keep searching.',
            'request' => $request,
            'dispatch' => [
                'offered' => (bool) $offer,
                'offered_unit_id' => $offer?->ambulance_unit_id,
                'assigned' => false,
            ],
        ];
    }

    public function createFromSignal(EmergencySosSignal $signal, int $userId): array
    {
        return $this->store($userId, [
            'latitude' => (float) $signal->latitude,
            'longitude' => (float) $signal->longitude,
            'sos_signal_id' => $signal->id,
            'description' => $signal->raw_transcript,
            'severity_level' => $signal->severity_level,
            'suspected_condition' => $signal->suspected_condition,
            'first_aid_steps' => $signal->first_aid_steps,
        ], true);
    }

    /**
     * Cancel abandoned SOS / missions for this user so they can request again.
     */
    public function autoExpireStaleForUser(int $userId): int
    {
        $count = 0;
        $pendingCutoff = now()->subMinutes(self::pendingExpireMinutes());
        $assignedCutoff = now()->subHours(self::assignedExpireHours());

        $stalePending = EmergencyRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('requested_at', '<', $pendingCutoff)
            ->lockForUpdate()
            ->get();

        foreach ($stalePending as $request) {
            $this->dispatch->cancel($request, $userId, 'auto_expired_no_driver_response');
            $count++;
        }

        $staleAssigned = EmergencyRequest::where('user_id', $userId)
            ->whereIn('status', ['assigned', 'arrived'])
            ->where(function ($q) use ($assignedCutoff) {
                $q->where('accepted_at', '<', $assignedCutoff)
                    ->orWhere(function ($q2) use ($assignedCutoff) {
                        $q2->whereNull('accepted_at')
                            ->where('requested_at', '<', $assignedCutoff);
                    });
            })
            ->lockForUpdate()
            ->get();

        foreach ($staleAssigned as $request) {
            // Force-cancel even if status is arrived (patient cancel only allows pending|assigned).
            $this->forceCancelStaleMission($request, 'auto_expired_stale_mission');
            $count++;
        }

        return $count;
    }

    private function forceCancelStaleMission(EmergencyRequest $request, string $reason): void
    {
        if ($request->ambulance_unit_id) {
            $unit = \Modules\Ambulance\Models\AmbulanceUnit::find($request->ambulance_unit_id);
            if ($unit) {
                $this->dispatch->releaseUnit($unit);
            }
        }

        \Modules\Ambulance\Models\EmergencyRequestOffer::query()
            ->where('emergency_request_id', $request->id)
            ->where('status', 'offered')
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);

        $request->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'ambulance_unit_id' => null,
            'escalated_at' => $request->escalated_at ?? now(),
        ]);
    }

    private function serializeActive(EmergencyRequest $request): array
    {
        $unit = $request->ambulanceUnit;
        $driver = $unit?->driver;

        return [
            'id' => $request->id,
            'case_number' => $request->case_number,
            'status' => $request->status,
            'priority' => $request->priority,
            'requested_at' => optional($request->requested_at)->toDateTimeString(),
            'accepted_at' => optional($request->accepted_at)->toDateTimeString(),
            'escalated_at' => optional($request->escalated_at)->toDateTimeString(),
            'can_cancel' => $request->status === 'pending',
            'lifecycle' => self::lifecycleMeta(),
            'patient_location' => [
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
            ],
            'ambulance' => $unit ? [
                'id' => $unit->id,
                'plate_number' => $unit->plate_number,
                'latitude' => $unit->latitude !== null ? (float) $unit->latitude : null,
                'longitude' => $unit->longitude !== null ? (float) $unit->longitude : null,
            ] : null,
            'driver' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
            ] : null,
        ];
    }
}
