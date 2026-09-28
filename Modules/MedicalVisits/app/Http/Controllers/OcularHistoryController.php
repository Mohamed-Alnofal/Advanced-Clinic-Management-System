<?php

namespace Modules\MedicalVisits\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\OcularHistoryService;

class OcularHistoryController extends Controller
{
    public function __construct(
        private OcularHistoryService $service
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->service->index()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_file_id' => 'required|exists:patient_files,id',
            'condition' => 'required|string',
            'diagnosed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'data' => $this->service->store($data)
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->show($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'condition' => 'sometimes|string',
            'diagnosed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'data' => $this->service->update($id, $data)
        ]);
    }

    public function destroy($id)
    {
        $this->service->destroy($id);

        return response()->json([
            'message' => 'Ocular history deleted successfully'
        ]);
    }
}