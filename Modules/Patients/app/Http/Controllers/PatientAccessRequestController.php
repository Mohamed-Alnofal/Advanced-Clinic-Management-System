<?php

namespace Modules\Patients\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Services\PatientAccessService;
use Modules\Patients\Services\PatientService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PatientAccessRequestController extends Controller
{
    public function __construct(
        private PatientAccessService $service
    ) {}

    public function requestAccess(Request $request)
    {
        $data = $request->validate([
            'visit_id' => 'required|exists:medical_visits,id',
        ]);

        $doctor = auth()->user()?->doctor;
        if (! $doctor) {
            return response()->json(['message' => 'Only doctors can request access.'], 403);
        }

        try {
            $accessRequest = $this->service->requestAccess(
                $doctor->id,
                MedicalVisits::findOrFail($data['visit_id'])
            );

            return response()->json([
                'message' => 'Access request sent to the patient.',
                'data' => $accessRequest,
            ]);
        } catch (AccessDeniedHttpException|ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function pendingRequests()
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $patient = app(PatientService::class)->createForUser($user);

        return response()->json([
            'data' => $this->service->getPendingRequests((int) $patient->id),
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|exists:patient_access_requests,id',
        ]);

        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            return response()->json([
                'message' => 'Access approved.',
                'data' => $this->service->approve($data['request_id'], $user),
            ]);
        } catch (AccessDeniedHttpException|ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function reject(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|exists:patient_access_requests,id',
        ]);

        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            return response()->json([
                'message' => 'Access rejected.',
                'data' => $this->service->reject($data['request_id'], $user),
            ]);
        } catch (AccessDeniedHttpException|ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
