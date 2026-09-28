<?php

namespace Modules\Patients\Services;

use Illuminate\Support\Facades\DB;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Modules\Patients\Models\PatientFile;
use Modules\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Who may read/write standing chart data (vitals, surgeries, family history…):
 * - patient owner of the chart
 * - doctor who created a visit on the chart, OR has approved access, OR chart has a public visit
 */
class PatientChartAccessService
{
    public function assertCanAccessFile(PatientFile $file, User $user, bool $write = false): void
    {
        if (! $this->canAccessFile($file, $user, $write)) {
            throw new AccessDeniedHttpException('Unauthorized for this patient chart.');
        }
    }

    public function canAccessFile(PatientFile $file, User $user, bool $write = false): bool
    {
        if ($user->patient && (int) $user->patient->id === (int) $file->patient_id) {
            return true; // patient owns the unified chart
        }

        if (! $user->doctor) {
            return false;
        }

        $doctorId = (int) $user->doctor->id;

        // Owning any visit on this chart
        if (DB::table('medical_visits')
            ->where('patient_file_id', $file->id)
            ->where('doctor_id', $doctorId)
            ->exists()) {
            return true;
        }

        // Chart creator
        if ((int) $file->created_by === $doctorId) {
            return true;
        }

        // Public visit on chart
        if (DB::table('medical_visits')
            ->where('patient_file_id', $file->id)
            ->where('visibility', MedicalVisitService::VISIBILITY_PUBLIC)
            ->exists()) {
            return true;
        }

        // Approved access to any visit on chart
        return DB::table('patient_access_requests')
            ->where('patient_file_id', $file->id)
            ->where('doctor_id', $doctorId)
            ->where('status', 'approved')
            ->exists();
    }

    public function accessibleFileIdsForUser(User $user): array
    {
        if ($user->patient) {
            return PatientFile::where('patient_id', $user->patient->id)->pluck('id')->all();
        }

        if (! $user->doctor) {
            return [];
        }

        $doctorId = (int) $user->doctor->id;

        $owned = DB::table('medical_visits')->where('doctor_id', $doctorId)->pluck('patient_file_id');
        $created = PatientFile::where('created_by', $doctorId)->pluck('id');
        $public = DB::table('medical_visits')
            ->where('visibility', MedicalVisitService::VISIBILITY_PUBLIC)
            ->pluck('patient_file_id');
        $approved = DB::table('patient_access_requests')
            ->where('doctor_id', $doctorId)
            ->where('status', 'approved')
            ->pluck('patient_file_id');

        return $owned->merge($created)->merge($public)->merge($approved)->unique()->values()->all();
    }
}
