<?php

namespace Modules\MedicalVisits\Services;

use Modules\MedicalVisits\Models\FamilyHistory;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientChartAccessService;
use Modules\Users\Models\User;

class FamilyHistoryService
{
    public function __construct(
        private PatientChartAccessService $chartAccess,
    ) {}

    public function indexForUser(User $user)
    {
        $fileIds = $this->chartAccess->accessibleFileIdsForUser($user);

        return FamilyHistory::with('patientFile')
            ->whereIn('patient_file_id', $fileIds)
            ->latest()
            ->paginate(10);
    }

    public function showForUser(int $id, User $user)
    {
        $record = FamilyHistory::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($record->patientFile, $user);

        return $record;
    }

    public function storeForUser(array $data, User $user)
    {
        $file = PatientFile::findOrFail($data['patient_file_id']);
        $this->chartAccess->assertCanAccessFile($file, $user, true);

        return FamilyHistory::create($data);
    }

    public function updateForUser(int $id, array $data, User $user)
    {
        $record = FamilyHistory::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($record->patientFile, $user, true);

        $record->update(collect($data)->only(['relative', 'disease'])->all());

        return $record->fresh();
    }

    public function deleteForUser(int $id, User $user): void
    {
        $record = FamilyHistory::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($record->patientFile, $user, true);
        $record->delete();
    }
}
