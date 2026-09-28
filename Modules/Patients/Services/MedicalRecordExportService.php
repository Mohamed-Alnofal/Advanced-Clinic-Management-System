<?php

namespace Modules\Patients\Services;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;
use Modules\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Builds a privacy-filtered JSON package of the unified medical chart
 * for AI / clinical transfer (patient full chart; doctor sees allowed visits only).
 */
class MedicalRecordExportService
{
    public function __construct(
        private MedicalVisitService $visits,
    ) {}

    public function exportForUser(int $patientId, User $user): array
    {
        $patient = Patients::with('patientable')->findOrFail($patientId);
        $file = PatientFile::where('patient_id', $patientId)->first();

        if (! $file) {
            throw new AccessDeniedHttpException('No unified chart found for this patient.');
        }

        $isOwner = $user->patient && (int) $user->patient->id === $patientId;
        $doctor = $user->doctor;

        if (! $isOwner && ! $doctor) {
            throw new AccessDeniedHttpException('Unauthorized to export this chart.');
        }

        $visitsQuery = MedicalVisits::query()
            ->where('patient_file_id', $file->id)
            ->with(['doctor.user:id,name', 'prescriptions.items', 'attachments'])
            ->latest('visit_date');

        if ($doctor && ! $isOwner) {
            $this->visits->scopeVisibleToDoctor($visitsQuery, $doctor->id);
        }

        $visits = $visitsQuery->get();

        return [
            'exported_at' => now()->toIso8601String(),
            'format' => 'acms.unified_medical_chart.v1',
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->patientable->name ?? null,
                'age' => $patient->patientable->age ?? null,
                'gender' => $patient->patientable->gender ?? null,
                'type' => class_basename($patient->patientable_type),
            ],
            'patient_file' => [
                'id' => $file->id,
                'patient_id' => $file->patient_id,
            ],
            'standing_history' => [
                'vital_signs' => $file->vitalSigns()->latest()->get(),
                'surgeries' => $file->surgeries()->latest()->get(),
                'family_histories' => $file->familyHistories()->latest()->get(),
                'ocular_histories' => $file->ocularHistories()->latest()->get(),
            ],
            'medical_visits' => $visits->map(fn (MedicalVisits $v) => [
                'id' => $v->id,
                'doctor' => $v->doctor?->user?->name,
                'chief_complaint' => $v->chief_complaint,
                'diagnosis' => $v->diagnosis,
                'notes' => $v->notes,
                'treatment_plan' => $v->treatment_plan,
                'visit_date' => $v->visit_date,
                'category' => $v->category,
                'visibility' => $v->visibility,
                'prescriptions' => $v->prescriptions,
                'attachments' => $v->attachments,
            ])->values(),
            'privacy_note' => $isOwner
                ? 'Full patient-owned chart export.'
                : 'Doctor export filtered by visit visibility and approved access.',
        ];
    }
}
