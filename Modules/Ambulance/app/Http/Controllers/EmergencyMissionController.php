<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Services\AmbulanceDispatchService;
use Modules\Ambulance\Services\NearestAmbulanceService;
use Modules\Core\Http\Controllers\Controller;

class EmergencyMissionController extends Controller
{
    public function __construct(
        private AmbulanceDispatchService $dispatch,
        private NearestAmbulanceService $nearest,
    ) {}

    public function history(Request $request): JsonResponse
    {
        $items = EmergencyRequest::with(['ambulanceUnit.driver', 'sosSignal'])
            ->where('user_id', auth()->id())
            ->whereIn('status', ['completed', 'cancelled'])
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    public function companyHistory(Request $request): JsonResponse
    {
        $companyId = auth()->user()?->ambulanceCompany?->id
            ?? \Modules\Ambulance\Models\AmbulanceCompany::where('user_id', auth()->id())->value('id');

        if (! $companyId) {
            return response()->json(['message' => 'Ambulance company not found.'], 404);
        }

        $items = EmergencyRequest::with(['ambulanceUnit.driver', 'user:id,name,phone', 'sosSignal'])
            ->where('ambulance_company_id', $companyId)
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $emergency = EmergencyRequest::where('user_id', auth()->id())->findOrFail($id);
            $result = $this->dispatch->cancel($emergency, auth()->id(), $data['reason'] ?? null);

            return response()->json([
                'message' => 'Emergency request cancelled.',
                'data' => $result,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Request not found or can no longer be cancelled (driver may have accepted).',
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function eta(int $id): JsonResponse
    {
        $emergency = EmergencyRequest::with(['ambulanceUnit.driver'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $unit = $emergency->ambulanceUnit;
        if (! $unit || $unit->latitude === null || $unit->longitude === null) {
            return response()->json([
                'message' => 'Ambulance not assigned or location unavailable.',
                'data' => [
                    'status' => $emergency->status,
                    'eta_minutes' => null,
                ],
            ]);
        }

        $distance = $this->nearest->distanceKm(
            (float) $emergency->latitude,
            (float) $emergency->longitude,
            (float) $unit->latitude,
            (float) $unit->longitude
        );
        $eta = $this->nearest->etaMinutes($distance);
        $driver = $unit->driver;

        return response()->json([
            'data' => [
                'emergency_request_id' => $emergency->id,
                'case_number' => $emergency->case_number,
                'status' => $emergency->status,
                'accepted_at' => $emergency->accepted_at,
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                ] : null,
                'ambulance' => [
                    'id' => $unit->id,
                    'plate_number' => $unit->plate_number,
                    'status' => $unit->status,
                    'latitude' => (float) $unit->latitude,
                    'longitude' => (float) $unit->longitude,
                    'last_location_update' => $unit->last_location_update,
                ],
                'distance_km' => round($distance, 2),
                'eta_minutes' => $eta,
            ],
        ]);
    }

    public function track(int $id): JsonResponse
    {
        return $this->eta($id);
    }

    public function myRequest(Request $request): JsonResponse
    {
        $emergency = EmergencyRequest::with(['ambulanceUnit.driver', 'sosSignal'])
            ->where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'assigned', 'arrived'])
            ->latest()
            ->first();

        if (! $emergency) {
            return response()->json(['data' => null]);
        }

        $unit = $emergency->ambulanceUnit;
        $driver = $unit?->driver;
        $etaMinutes = null;
        $distanceKm = null;

        if ($unit && $unit->latitude !== null && $unit->longitude !== null) {
            $distanceKm = round($this->nearest->distanceKm(
                (float) $emergency->latitude,
                (float) $emergency->longitude,
                (float) $unit->latitude,
                (float) $unit->longitude
            ), 2);
            $etaMinutes = $this->nearest->etaMinutes($distanceKm);
        }

        return response()->json([
            'data' => [
                'id' => $emergency->id,
                'case_number' => $emergency->case_number,
                'status' => $emergency->status,
                'priority' => $emergency->priority,
                'can_cancel' => $emergency->status === 'pending',
                'escalated_at' => $emergency->escalated_at,
                'patient_location' => [
                    'latitude' => (float) $emergency->latitude,
                    'longitude' => (float) $emergency->longitude,
                ],
                'requested_at' => $emergency->requested_at,
                'accepted_at' => $emergency->accepted_at,
                'arrived_at' => $emergency->arrived_at,
                'assigned_ambulance' => $unit ? [
                    'id' => $unit->id,
                    'plate_number' => $unit->plate_number,
                    'status' => $unit->status,
                    'latitude' => $unit->latitude !== null ? (float) $unit->latitude : null,
                    'longitude' => $unit->longitude !== null ? (float) $unit->longitude : null,
                    'last_location_update' => $unit->last_location_update,
                ] : null,
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                ] : null,
                'distance_km' => $distanceKm,
                'eta_minutes' => $etaMinutes,
                'eta_note' => $etaMinutes !== null
                    ? 'ETA from last GPS update of the ambulance (avg ~35 km/h).'
                    : 'Waiting for ambulance GPS updates.',
                'lifecycle' => \Modules\Ambulance\Services\EmergencyRequestService::lifecycleMeta(),
            ],
        ]);
    }
}
