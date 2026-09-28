<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Models\EmergencyRequestOffer;
use Modules\Ambulance\Services\AmbulancePatientNotifier;

class AmbulanceDispatchService
{
    public function __construct(
        private NearestAmbulanceService $nearest,
    ) {}

    /**
     * Pending SOS with no live offer — keep searching (driver may have come online later).
     */
    public function redispatchPendingWithoutOffers(int $limit = 30): int
    {
        $count = 0;

        EmergencyRequest::query()
            ->where('status', 'pending')
            ->whereDoesntHave('offers', fn ($q) => $q->where('status', 'offered'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (EmergencyRequest $request) use (&$count) {
                if ($this->offerToNearest($request)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * When a unit becomes available, offer it any nearby unanswered SOS.
     */
    public function offerNearbyPendingToUnit(AmbulanceUnit $unit, float $maxKm = 50.0): int
    {
        if ($unit->status !== 'available'
            || $unit->ambulance_driver_id === null
            || $unit->latitude === null
            || $unit->longitude === null
        ) {
            return 0;
        }

        $count = 0;

        EmergencyRequest::query()
            ->where('status', 'pending')
            ->whereDoesntHave('offers', fn ($q) => $q->where('status', 'offered'))
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->each(function (EmergencyRequest $request) use ($unit, $maxKm, &$count) {
                $distance = $this->nearest->distanceKm(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $unit->latitude,
                    (float) $unit->longitude
                );

                if ($distance > $maxKm) {
                    return;
                }

                try {
                    $this->offerToUnit($request, $unit->id);
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('offerNearbyPendingToUnit failed', [
                        'request_id' => $request->id,
                        'unit_id' => $unit->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $count;
    }

    /**
     * Notify the nearest available driver without assigning the request or marking the unit busy.
     */
    public function offerToNearest(EmergencyRequest $request, array $excludeUnitIds = []): ?EmergencyRequestOffer
    {
        return DB::transaction(function () use ($request, $excludeUnitIds) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return null;
            }

            $this->expireActiveOffers($locked->id);

            $rejectedUnitIds = EmergencyRequestOffer::query()
                ->where('emergency_request_id', $locked->id)
                ->where('status', 'rejected')
                ->pluck('ambulance_unit_id')
                ->all();

            $exclude = array_values(array_unique(array_merge($excludeUnitIds, $rejectedUnitIds)));
            $attemptExclude = $exclude;

            while (true) {
                $nearest = $this->nearest->findNearest(
                    (float) $locked->latitude,
                    (float) $locked->longitude,
                    50.0,
                    $attemptExclude
                );

                if (! $nearest) {
                    return null;
                }

                $unit = AmbulanceUnit::whereKey($nearest->id)
                    ->where('status', 'available')
                    ->whereNotNull('ambulance_driver_id')
                    ->lockForUpdate()
                    ->first();

                if (! $unit) {
                    $attemptExclude[] = $nearest->id;

                    continue;
                }

                $offer = EmergencyRequestOffer::updateOrCreate(
                    [
                        'emergency_request_id' => $locked->id,
                        'ambulance_unit_id' => $unit->id,
                    ],
                    [
                        'ambulance_driver_id' => $unit->ambulance_driver_id,
                        'status' => 'offered',
                        'offered_at' => now(),
                        'responded_at' => null,
                    ]
                );

                $this->notifyDriver($locked->fresh(), $unit);

                return $offer;
            }
        });
    }

    /**
     * Company portal: offer a specific unit without assigning the request.
     */
    public function offerToUnit(EmergencyRequest $request, int $unitId): EmergencyRequestOffer
    {
        return DB::transaction(function () use ($request, $unitId) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $this->expireActiveOffers($locked->id);

            $unit = AmbulanceUnit::whereKey($unitId)
                ->where('status', 'available')
                ->whereNotNull('ambulance_driver_id')
                ->lockForUpdate()
                ->firstOrFail();

            $offer = EmergencyRequestOffer::updateOrCreate(
                [
                    'emergency_request_id' => $locked->id,
                    'ambulance_unit_id' => $unit->id,
                ],
                [
                    'ambulance_driver_id' => $unit->ambulance_driver_id,
                    'status' => 'offered',
                    'offered_at' => now(),
                    'responded_at' => null,
                ]
            );

            $this->notifyDriver($locked->fresh(), $unit);

            return $offer;
        });
    }

    public function acceptByDriver(EmergencyRequest $request, int $driverId): EmergencyRequest
    {
        $wasPending = $request->status === 'pending';

        $fresh = DB::transaction(function () use ($request, $driverId) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'assigned') {
                $this->assertDriverOwns($locked, $driverId);

                if (! $locked->accepted_at) {
                    $locked->update(['accepted_at' => now()]);
                }

                return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
            }

            if ($locked->status !== 'pending') {
                throw new \RuntimeException('Request is no longer available.');
            }

            $offer = EmergencyRequestOffer::where('emergency_request_id', $locked->id)
                ->where('ambulance_driver_id', $driverId)
                ->where('status', 'offered')
                ->lockForUpdate()
                ->firstOrFail();

            $unit = AmbulanceUnit::whereKey($offer->ambulance_unit_id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'ambulance_unit_id' => $unit->id,
                'ambulance_company_id' => $unit->ambulance_company_id,
                'status' => 'assigned',
                'accepted_at' => now(),
            ]);

            $unit->update(['status' => 'busy']);

            $offer->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $this->expireActiveOffers($locked->id, $offer->id);

            return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
        });

        if ($wasPending && $fresh && $fresh->status === 'assigned') {
            app(AmbulancePatientNotifier::class)->notify($fresh, 'accepted');
        }

        return $fresh;
    }

    public function rejectByDriver(EmergencyRequest $request, int $driverId): EmergencyRequest
    {
        $locked = DB::transaction(function () use ($request, $driverId) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $offer = EmergencyRequestOffer::where('emergency_request_id', $locked->id)
                ->where('ambulance_driver_id', $driverId)
                ->where('status', 'offered')
                ->lockForUpdate()
                ->firstOrFail();

            $offer->update([
                'status' => 'rejected',
                'responded_at' => now(),
            ]);

            return $locked;
        });

        $this->offerToNearest($locked);

        return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
    }

    public function markArrived(EmergencyRequest $request, int $driverId): EmergencyRequest
    {
        $fresh = DB::transaction(function () use ($request, $driverId) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->whereIn('status', ['assigned'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDriverOwns($locked, $driverId);

            $locked->update([
                'status' => 'arrived',
                'arrived_at' => now(),
            ]);

            return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
        });

        app(AmbulancePatientNotifier::class)->notify($fresh, 'arrived');

        return $fresh;
    }

    public function complete(EmergencyRequest $request, int $driverId): EmergencyRequest
    {
        $fresh = DB::transaction(function () use ($request, $driverId) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->whereIn('status', ['assigned', 'arrived'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDriverOwns($locked, $driverId);

            $unit = AmbulanceUnit::whereKey($locked->ambulance_unit_id)
                ->lockForUpdate()
                ->first();

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($unit) {
                $unit->update(['status' => 'available']);
            }

            return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
        });

        app(AmbulancePatientNotifier::class)->notify($fresh, 'completed');

        return $fresh;
    }

    public function cancel(EmergencyRequest $request, int $userId, ?string $reason = null): EmergencyRequest
    {
        return DB::transaction(function () use ($request, $userId, $reason) {
            $locked = EmergencyRequest::whereKey($request->id)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $this->expireActiveOffers($locked->id);

            $locked->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason ?? 'cancelled_by_patient',
            ]);

            return $locked->fresh(['ambulanceUnit.driver', 'sosSignal']);
        });
    }

    public function releaseUnit(AmbulanceUnit $unit): void
    {
        $unit->update(['status' => 'available']);
    }

    private function expireActiveOffers(int $requestId, ?int $exceptOfferId = null): void
    {
        EmergencyRequestOffer::query()
            ->where('emergency_request_id', $requestId)
            ->where('status', 'offered')
            ->when($exceptOfferId, fn ($q) => $q->where('id', '!=', $exceptOfferId))
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);
    }

    private function assertDriverOwns(EmergencyRequest $request, int $driverId): void
    {
        $unit = AmbulanceUnit::find($request->ambulance_unit_id);
        if (! $unit || (int) $unit->ambulance_driver_id !== $driverId) {
            throw new \RuntimeException('This mission is assigned to another driver.');
        }
    }

    private function notifyDriver(EmergencyRequest $request, AmbulanceUnit $unit): void
    {
        $token = $unit->device_token;
        if (! $token) {
            Log::info('Ambulance dispatch: no device_token for unit', [
                'unit_id' => $unit->id,
                'request_id' => $request->id,
            ]);

            return;
        }

        try {
            if (class_exists(\Modules\Notifications\Services\FirebaseService::class)) {
                app(\Modules\Notifications\Services\FirebaseService::class)->sendToToken(
                    $token,
                    'مهمة إسعاف جديدة',
                    'لديك طلب طوارئ قريب — افتح التطبيق للقبول أو الرفض.',
                    [
                        'type' => 'ambulance_dispatch',
                        'emergency_request_id' => (string) $request->id,
                        'case_number' => (string) $request->case_number,
                        'priority' => (string) $request->priority,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Ambulance FCM notify failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
