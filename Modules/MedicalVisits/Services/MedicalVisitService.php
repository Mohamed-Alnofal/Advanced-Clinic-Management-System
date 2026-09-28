<?php

namespace Modules\MedicalVisits\Services;

use Illuminate\Support\Facades\DB;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientService;
use Modules\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Unified chart privacy (patient-owned):
 * - private: owning doctor + patient only
 * - approved_doctors: owning doctor + doctors with approved access request
 * - public: any authenticated doctor
 *
 * Doctors create clinical visits; they do NOT choose who else may read them.
 * Default on create = approved_doctors. Patients change visibility later.
 */
class MedicalVisitService
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_APPROVED = 'approved_doctors';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_APPROVED,
        self::VISIBILITY_PRIVATE,
    ];

    public function indexForUser(User $user)
    {
        $query = MedicalVisits::with(['patientFile', 'doctor.user'])->latest();

        if ($user->doctor) {
            return $this->scopeVisibleToDoctor($query, $user->doctor->id)->paginate(10);
        }

        if ($user->patient) {
            $fileIds = PatientFile::where('patient_id', $user->patient->id)->pluck('id');

            return $query->whereIn('patient_file_id', $fileIds)->paginate(10);
        }

        app(PatientService::class)->createForUser($user);
        $user->load('patient');

        if ($user->patient) {
            $fileIds = PatientFile::where('patient_id', $user->patient->id)->pluck('id');

            return $query->whereIn('patient_file_id', $fileIds)->paginate(10);
        }

        throw new AccessDeniedHttpException('Unauthorized to list medical visits.');
    }

    public function showForUser(int $id, User $user)
    {
        $visit = MedicalVisits::with(['patientFile', 'doctor.user', 'prescriptions.items', 'attachments','laboratoryOrders.result'])
            ->findOrFail($id);

        if (! $this->canAccessVisit($visit, $user)) {
            throw new AccessDeniedHttpException('Unauthorized to view this medical visit.');
        }

        return $visit;
    }

    public function store(array $data, $doctor)
    {
        // Patient-owned privacy: doctors never pick visibility on create.
        return MedicalVisits::create([
            'patient_file_id' => $data['patient_file_id'],
            'doctor_id' => $doctor->id,
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'diagnosis' => $data['diagnosis'],
            'icd_code' => $data['icd_code'] ?? null,
            'notes' => $data['notes'] ?? null,
            'treatment_plan' => $data['treatment_plan'] ?? null,
            'visit_date' => $data['visit_date'],
            'category' => $data['category'],
            'visibility' => self::VISIBILITY_APPROVED,
            'speciality_data' => $data['speciality_data'] ?? null,
        ]);
    }

    public function updateForUser(int $id, array $data, User $user)
    {
        $visit = MedicalVisits::findOrFail($id);

        if ($user->doctor && $visit->doctor_id === $user->doctor->id) {
            unset($data['visibility']); // doctor cannot change privacy

            $visit->update(collect($data)->only([
                'chief_complaint',
                'diagnosis',
                'icd_code',
                'notes',
                'treatment_plan',
                'visit_date',
                'category',
                'speciality_data',
            ])->filter(fn ($v) => $v !== null)->all());

            return $visit->fresh(['doctor.user', 'patientFile']);
        }

        throw new AccessDeniedHttpException('Only the owning doctor can update clinical fields.');
    }

    /**
     * Patient changes who may see this visit entry inside the unified chart.
     */
    public function updateVisibilityForPatient(int $id, string $visibility, User $user): MedicalVisits
    {
        if (! in_array($visibility, self::VISIBILITIES, true)) {
            throw new AccessDeniedHttpException('Invalid visibility.');
        }

        $visit = MedicalVisits::with('patientFile')->findOrFail($id);

        if (! $user->patient || (int) $visit->patientFile?->patient_id !== (int) $user->patient->id) {
            throw new AccessDeniedHttpException('Only the patient can change visit privacy.');
        }

        $visit->update(['visibility' => $visibility]);

        return $visit->fresh(['doctor.user', 'patientFile']);
    }

    public function deleteForUser(int $id, User $user): void
    {
        $visit = MedicalVisits::findOrFail($id);

        if (! $user->doctor || $visit->doctor_id !== $user->doctor->id) {
            throw new AccessDeniedHttpException('Only the owning doctor can delete this visit.');
        }

        $visit->delete();
    }

    public function scopeVisibleToDoctor($query, int $doctorId)
    {
        return $query->where(function ($q) use ($doctorId) {
            $q->where('doctor_id', $doctorId)
                ->orWhere('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere(function ($approved) use ($doctorId) {
                    $approved->where('visibility', self::VISIBILITY_APPROVED)
                        ->whereExists(function ($sub) use ($doctorId) {
                            $sub->selectRaw(1)
                                ->from('patient_access_requests')
                                ->whereColumn('patient_access_requests.medical_visit_id', 'medical_visits.id')
                                ->where('patient_access_requests.doctor_id', $doctorId)
                                ->where('patient_access_requests.status', 'approved');
                        });
                });
        });
    }

    public function canAccessVisit(MedicalVisits $visit, User $user): bool
    {
        if ($user->doctor) {
            if ((int) $visit->doctor_id === (int) $user->doctor->id) {
                return true;
            }

            if ($visit->visibility === self::VISIBILITY_PUBLIC) {
                return true;
            }

            if ($visit->visibility === self::VISIBILITY_PRIVATE) {
                return false;
            }

            // approved_doctors
            return DB::table('patient_access_requests')
                ->where('medical_visit_id', $visit->id)
                ->where('doctor_id', $user->doctor->id)
                ->where('status', 'approved')
                ->exists();
        }

        if ($user->patient) {
            return PatientFile::where('id', $visit->patient_file_id)
                ->where('patient_id', $user->patient->id)
                ->exists();
        }

        $patient = app(PatientService::class)->createForUser($user);

        return PatientFile::where('id', $visit->patient_file_id)
            ->where('patient_id', $patient->id)
            ->exists();
    }
}
