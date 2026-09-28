<?php

namespace Modules\MedicalVisits\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\FamilyHistoryService;

class FamilyHistoryController extends Controller
{
    public function __construct(private FamilyHistoryService $service) {}

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
            'relative' => 'required|string|max:255',
            'disease' => 'required|string|max:255',
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
            'relative' => 'sometimes|string|max:255',
            'disease' => 'sometimes|string|max:255',
        ]);

        return response()->json([
            'data' => $this->service->updateForUser((int) $id, $data, auth()->user()),
        ]);
    }

    public function destroy($id)
    {
        $this->service->deleteForUser((int) $id, auth()->user());

        return response()->json([
            'message' => 'Family history deleted successfully',
        ]);
    }
}
