<?php

namespace Modules\Patients\Services;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Modules\Patients\Models\PatientAccessRequest;
use Modules\Patients\Models\Patients;
use Modules\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PatientAccessService
{
    public function requestAccess(int $doctorId, MedicalVisits $visit): PatientAccessRequest
    {
        $visit->loadMissing('patientFile');

        if ((int) $visit->doctor_id === $doctorId) {
            throw new ConflictHttpException('You already own this visit.');
        }

        if ($visit->visibility === MedicalVisitService::VISIBILITY_PRIVATE) {
            throw new AccessDeniedHttpException('This visit is private. The patient must change privacy first.');
        }

        if ($visit->visibility === MedicalVisitService::VISIBILITY_PUBLIC) {
            throw new ConflictHttpException('This visit is already public to doctors.');
        }

        $existing = PatientAccessRequest::query()
            ->where('medical_visit_id', $visit->id)
            ->where('doctor_id', $doctorId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return PatientAccessRequest::create([
            'doctor_id' => $doctorId,
            'patient_id' => $visit->patientFile->patient_id,
            'patient_file_id' => $visit->patient_file_id,
            'medical_visit_id' => $visit->id,
            'status' => 'pending',
        ]);
    }

    public function getPendingRequests(int $patientId)
    {
        return PatientAccessRequest::with([
            'doctor.user:id,name,phone',
            'visit:id,diagnosis,visit_date,visibility,chief_complaint',
        ])
            ->where('patient_id', $patientId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function approve(int $requestId, User $user): PatientAccessRequest
    {
        $request = PatientAccessRequest::with('patient')->findOrFail($requestId);

        if (! $this->userOwnsPatientRecord($user, (int) $request->patient_id)) {
            throw new AccessDeniedHttpException('You can only approve your own access requests.');
        }

        if ($request->status !== 'pending') {
            throw new ConflictHttpException('Request is no longer pending.');
        }

        $request->update(['status' => 'approved']);

        return $request->fresh(['doctor.user', 'visit']);
    }

    public function reject(int $requestId, User $user): PatientAccessRequest
    {
        $request = PatientAccessRequest::with('patient')->findOrFail($requestId);

        if (! $this->userOwnsPatientRecord($user, (int) $request->patient_id)) {
            throw new AccessDeniedHttpException('You can only reject your own access requests.');
        }

        if ($request->status !== 'pending') {
            throw new ConflictHttpException('Request is no longer pending.');
        }

        $request->update(['status' => 'rejected']);

        return $request->fresh(['doctor.user', 'visit']);
    }

    private function userOwnsPatientRecord(User $user, int $patientId): bool
    {
        $patient = \Modules\Patients\Models\Patients::find($patientId);
        if (! $patient) {
            return false;
        }

        return $patient->patientable_type === User::class
            && (int) $patient->patientable_id === (int) $user->id;
    }
}
