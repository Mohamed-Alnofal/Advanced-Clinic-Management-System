<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Core\Http\Controllers\Controller;

class TrackingController extends Controller
{
    /**
     * Company portal GPS update for a unit they own.
     * Drivers should use POST /api/driver/tracking/location instead.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ambulance_unit_id' => 'required|exists:ambulance_units,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $unit = AmbulanceUnit::findOrFail($data['ambulance_unit_id']);
        $owns = AmbulanceCompany::where('user_id', auth()->id())
            ->where('id', $unit->ambulance_company_id)
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Unauthorized to update this unit location.'], 403);
        }

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
}
