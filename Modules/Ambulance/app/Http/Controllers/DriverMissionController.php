<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Services\AmbulanceDispatchService;
use Modules\Ambulance\Services\AmbulanceDriverService;
use Modules\Ambulance\Services\NearestAmbulanceService;
use Modules\Core\Http\Controllers\Controller;

class DriverMissionController extends Controller
{
    public function __construct(
        private AmbulanceDispatchService $dispatch,
        private AmbulanceDriverService $driverService,
        private NearestAmbulanceService $nearest,
    ) {}

    public function requests(Request $request): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();
        $unit = AmbulanceUnit::where('ambulance_driver_id', $driver->id)->first();

        if (! $unit) {
            return response()->json(['data' => [], 'message' => 'No unit paired to this driver.']);
        }

        $offers = $this->driverService->pendingRequestOffers($driver->id);

        $data = $offers->map(function ($offer) use ($unit) {
            $emergency = $offer->emergencyRequest;
            $distance = $this->nearest->distanceKm(
                (float) $unit->latitude,
                (float) $unit->longitude,
                (float) $emergency->latitude,
                (float) $emergency->longitude
            );

            return [
                'offer_id' => $offer->id,
                'id' => $emergency->id,
                'case_number' => $emergency->case_number,
                'priority' => $emergency->priority,
                'patient_location' => [
                    'latitude' => (float) $emergency->latitude,
                    'longitude' => (float) $emergency->longitude,
                ],
                'distance_km' => round($distance, 2),
                'requested_at' => $emergency->requested_at,
                'offered_at' => $offer->offered_at,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function active(Request $request): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();
        $unit = AmbulanceUnit::where('ambulance_driver_id', $driver->id)->first();

        if (! $unit) {
            return response()->json(['data' => null, 'message' => 'No unit paired to this driver.']);
        }

        $mission = EmergencyRequest::with(['sosSignal', 'user:id,name,phone'])
            ->where('ambulance_unit_id', $unit->id)
            ->whereIn('status', ['assigned', 'arrived'])
            ->latest()
            ->first();

        if (! $mission) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $mission->id,
                'case_number' => $mission->case_number,
                'status' => $mission->status,
                'priority' => $mission->priority,
                'latitude' => (float) $mission->latitude,
                'longitude' => (float) $mission->longitude,
                'patient_location' => [
                    'latitude' => (float) $mission->latitude,
                    'longitude' => (float) $mission->longitude,
                ],
                'accepted_at' => $mission->accepted_at,
                'arrived_at' => $mission->arrived_at,
                'user' => $mission->user ? [
                    'id' => $mission->user->id,
                    'name' => $mission->user->name,
                    'phone' => $mission->user->phone,
                ] : null,
            ],
        ]);
    }

    public function accept(int $id): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();

        try {
            $emergency = EmergencyRequest::findOrFail($id);
            $result = $this->dispatch->acceptByDriver($emergency, $driver->id);

            return response()->json([
                'message' => 'Mission accepted.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function reject(int $id): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();

        try {
            $emergency = EmergencyRequest::findOrFail($id);
            $result = $this->dispatch->rejectByDriver($emergency, $driver->id);

            return response()->json([
                'message' => 'Mission rejected. Request offered to next nearest unit.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function arrived(int $id): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();

        try {
            $emergency = EmergencyRequest::findOrFail($id);
            $result = $this->dispatch->markArrived($emergency, $driver->id);

            return response()->json([
                'message' => 'Marked as arrived.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function complete(int $id): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();

        try {
            $emergency = EmergencyRequest::findOrFail($id);
            $result = $this->dispatch->complete($emergency, $driver->id);

            return response()->json([
                'message' => 'Mission completed. Unit is available again.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $driver = auth('ambulance-driver')->user();
        $unit = AmbulanceUnit::where('ambulance_driver_id', $driver->id)->firstOrFail();

        $unit->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'last_location_update' => now(),
        ]);

        return response()->json([
            'message' => 'Location updated.',
            'data' => [
                'id' => $unit->id,
                'latitude' => (float) $unit->latitude,
                'longitude' => (float) $unit->longitude,
                'status' => $unit->status,
                'last_location_update' => $unit->last_location_update,
            ],
        ]);
    }

    public function unit(): JsonResponse
    {
        $driver = auth('ambulance-driver')->user();
        $unit = AmbulanceUnit::where('ambulance_driver_id', $driver->id)->first();

        if (! $unit) {
            return response()->json([
                'message' => 'No unit paired to this driver.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'plate_number' => $unit->plate_number,
                'status' => $unit->status,
                'latitude' => $unit->latitude !== null ? (float) $unit->latitude : null,
                'longitude' => $unit->longitude !== null ? (float) $unit->longitude : null,
                'last_location_update' => $unit->last_location_update,
            ],
        ]);
    }

    /**
     * Driver goes on/off duty. Only available units receive SOS offers.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:available,offline',
        ]);

        $driver = auth('ambulance-driver')->user();
        $unit = AmbulanceUnit::where('ambulance_driver_id', $driver->id)->firstOrFail();

        // Do not let drivers override busy mid-mission via this endpoint.
        if ($unit->status === 'busy' && $data['status'] === 'available') {
            return response()->json([
                'message' => 'Unit is on an active mission. Complete it first.',
            ], 409);
        }

        if ($unit->status === 'busy' && $data['status'] === 'offline') {
            return response()->json([
                'message' => 'Unit is on an active mission. Complete it first.',
            ], 409);
        }

        $unit->update(['status' => $data['status']]);
        $unit->refresh();

        $offered = 0;
        if ($unit->status === 'available') {
            // Pick up SOS that arrived while this unit was offline.
            $offered = $this->dispatch->offerNearbyPendingToUnit($unit);
        }

        return response()->json([
            'message' => $offered > 0
                ? "Unit status updated. {$offered} nearby SOS offer(s) sent to you."
                : 'Unit status updated.',
            'data' => [
                'id' => $unit->id,
                'plate_number' => $unit->plate_number,
                'status' => $unit->status,
                'offers_created' => $offered,
            ],
        ]);
    }
}
