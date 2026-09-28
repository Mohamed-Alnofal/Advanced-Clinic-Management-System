<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Services\AmbulanceDispatchService;
use Modules\Core\Http\Controllers\Controller;

class DispatchController extends Controller
{
    public function __construct(
        private AmbulanceDispatchService $dispatch,
    ) {}

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'ambulance_unit_id' => 'required|exists:ambulance_units,id',
        ]);

        try {
            $emergency = EmergencyRequest::findOrFail($id);
            $result = $this->dispatch->offerToUnit($emergency, (int) $data['ambulance_unit_id']);

            return response()->json([
                'message' => 'Ambulance unit notified successfully.',
                'data' => $result->load('emergencyRequest', 'ambulanceUnit.driver'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Request is not pending or unit is not available.',
            ], 409);
        }
    }
}
