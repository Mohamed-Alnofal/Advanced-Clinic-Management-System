<?php

namespace Modules\MedicalVisits\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\OphthalmologyRecordService;

class OphthalmologyRecordController extends Controller
{
    public function __construct(
        private OphthalmologyRecordService $service
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->service->index()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->show($id)
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()?->doctor) {
            return response()->json(['message' => 'Only doctors can create ophthalmology records'], 403);
        }

        $data = $request->validate([
            'medical_visit_id' => 'required|exists:medical_visits,id',

            'visual_acuity_od' => 'nullable|string',
            'visual_acuity_os' => 'nullable|string',

            'iop_od' => 'nullable|numeric',
            'iop_os' => 'nullable|numeric',

            'cornea' => 'nullable|string',
            'lens' => 'nullable|string',
            'retina' => 'nullable|string',
            'optic_disc' => 'nullable|string',
            'slit_lamp_exam' => 'nullable|string',
            'refraction' => 'nullable|string',
        ]);

        return response()->json([
            'data' => $this->service->store($data)
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'visual_acuity_od' => 'sometimes|string',
            'visual_acuity_os' => 'sometimes|string',

            'iop_od' => 'sometimes|numeric',
            'iop_os' => 'sometimes|numeric',

            'cornea' => 'sometimes|string',
            'lens' => 'sometimes|string',
            'retina' => 'sometimes|string',
            'optic_disc' => 'sometimes|string',
            'slit_lamp_exam' => 'sometimes|string',
            'refraction' => 'sometimes|string',
        ]);

        return response()->json([
            'data' => $this->service->update($id, $data)
        ]);
    }

    public function destroy($id)
    {
        $this->service->destroy($id);

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}