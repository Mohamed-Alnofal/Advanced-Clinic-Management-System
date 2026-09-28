<?php

namespace Modules\MedicalVisits\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\VitalSignService;

class VitalSignController extends Controller
{
    public function __construct(
        private VitalSignService $service
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->service->indexForUser(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_file_id' => 'required|exists:patient_files,id',
            'blood_type' => 'nullable|string|max:5',
            'rh_factor' => 'nullable|string|max:5',
            'height' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
        ]);

        return response()->json([
            'data' => $this->service->storeForUser($data, auth()->user()),
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->showForUser((int) $id, auth()->user()),
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'blood_type' => 'nullable|string|max:5',
            'rh_factor' => 'nullable|string|max:5',
            'height' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
        ]);

        return response()->json([
            'data' => $this->service->updateForUser((int) $id, $data, auth()->user()),
        ]);
    }

    public function destroy($id)
    {
        $this->service->destroyForUser((int) $id, auth()->user());

        return response()->json([
            'message' => 'Vital sign deleted successfully',
        ]);
    }
}
