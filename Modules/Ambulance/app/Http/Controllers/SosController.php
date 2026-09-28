<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Services\EmergencyRequestService;
use Modules\Core\Http\Controllers\Controller;

class SosController extends Controller
{
    public function __construct(
        private EmergencyRequestService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'sos_signal_id' => 'nullable|integer|exists:emergency_sos_signals,id',
            'description' => 'nullable|string|max:2000',
        ]);

        $result = $this->service->store(auth()->id(), $data);

        $status = isset($result['request']) ? 201 : 409;

        return response()->json($result, $status);
    }

    public function show(int $id): JsonResponse
    {
        $request = EmergencyRequest::with(['ambulanceUnit.driver', 'sosSignal'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['data' => $request]);
    }
}
