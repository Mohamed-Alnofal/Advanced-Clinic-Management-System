<?php

namespace Modules\Patients\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Patients\Models\Patients;
use Modules\Patients\Services\PatientService;
use Modules\Patients\Services\MedicalRecordExportService;
use Modules\Users\Models\Relatives;
use Modules\Users\Models\User;

class PatientsController extends Controller
{
    public function __construct(
        private PatientService $service,
        private MedicalRecordExportService $exportService,
    ) {}

    // public function store(Request $request)
    // {
    //     $data = $request->validate([
    //         'type' => 'required|in:user,relative',
    //         'id' => 'required|integer'
    //     ]);

    //     $model = match ($data['type']) {
    //         'user' => User::findOrFail($data['id']),
    //         'relative' => Relatives::findOrFail($data['id']),
    //     };

    //     return $this->service->create($model);
    // }
    public function store(Request $request)
{
    $data = $request->validate([
        'type' => 'required|in:user,relative',
        'id' => 'required|integer'
    ]);

    $model = match ($data['type']) {
        'user' => User::findOrFail($data['id']),
        'relative' => Relatives::findOrFail($data['id']),
    };

    return response()->json([
        'data' => $this->service->create(
            $model,
            auth()->user()->doctor->id
        )
    ]);
}

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->getPatientWithOwner($id)
        ]);
    }

    public function me()
    {
        $user = auth()->user();
        $patient = $this->service->createForUser($user);

        return response()->json([
            'data' => $this->service->getPatientWithOwner($patient->id),
        ]);
    }

        public function patients(Request $request)
    {
        $data = $request->validate([
            'search' => 'required|string|min:1'
        ]);

        return response()->json([
            'data' => $this->service->searchPatients($data['search'])
        ]);
    }
    public function history(Patients $patient)
    {
        return response()->json([
            'data' => $this->service->history($patient->id, auth()->user()),
        ]);
    }

    public function export(Patients $patient)
    {
        return response()->json([
            'data' => $this->exportService->exportForUser($patient->id, auth()->user()),
        ]);
    }
}