<?php

namespace Modules\MedicalVisits\Services;

use Modules\MedicalVisits\Models\Surgery;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientChartAccessService;
use Modules\Users\Models\User;

class SurgeryService
{
    public function __construct(
        private PatientChartAccessService $chartAccess,
    ) {}

    public function indexForUser(User $user)
    {
        $fileIds = $this->chartAccess->accessibleFileIdsForUser($user);

        return Surgery::with('patientFile')
            ->whereIn('patient_file_id', $fileIds)
            ->latest()
            ->get();
    }

    public function showForUser(int $id, User $user)
    {
        $surgery = Surgery::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($surgery->patientFile, $user);

        return $surgery;
    }

    public function storeForUser(array $data, User $user)
    {
        $file = PatientFile::findOrFail($data['patient_file_id']);
        $this->chartAccess->assertCanAccessFile($file, $user, true);

        return Surgery::create($data);
    }

    public function updateForUser(int $id, array $data, User $user)
    {
        $surgery = Surgery::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($surgery->patientFile, $user, true);

        $surgery->update(collect($data)->only(['surgery_name', 'surgery_date', 'notes'])->all());

        return $surgery->fresh();
    }

    public function destroyForUser(int $id, User $user): void
    {
        $surgery = Surgery::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($surgery->patientFile, $user, true);
        $surgery->delete();
    }
}
