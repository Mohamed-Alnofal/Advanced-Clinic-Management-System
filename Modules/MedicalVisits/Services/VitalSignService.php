<?php

namespace Modules\MedicalVisits\Services;

use Modules\MedicalVisits\Models\VitalSign;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientChartAccessService;
use Modules\Users\Models\User;

class VitalSignService
{
    public function __construct(
        private PatientChartAccessService $chartAccess,
    ) {}

    public function indexForUser(User $user)
    {
        $fileIds = $this->chartAccess->accessibleFileIdsForUser($user);

        return VitalSign::with('patientFile')
            ->whereIn('patient_file_id', $fileIds)
            ->latest()
            ->get();
    }

    public function showForUser(int $id, User $user)
    {
        $vital = VitalSign::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($vital->patientFile, $user);

        return $vital;
    }

    public function storeForUser(array $data, User $user)
    {
        $file = PatientFile::findOrFail($data['patient_file_id']);
        $this->chartAccess->assertCanAccessFile($file, $user, true);

        return VitalSign::create($this->withBmi($data));
    }

    public function updateForUser(int $id, array $data, User $user)
    {
        $vital = VitalSign::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($vital->patientFile, $user, true);

        $vital->update($this->withBmi($data));

        return $vital->fresh();
    }

    public function destroyForUser(int $id, User $user): void
    {
        $vital = VitalSign::with('patientFile')->findOrFail($id);
        $this->chartAccess->assertCanAccessFile($vital->patientFile, $user, true);
        $vital->delete();
    }

    private function withBmi(array $data): array
    {
        if (! empty($data['height']) && ! empty($data['weight'])) {
            $heightInMeter = $data['height'] / 100;
            $data['bmi'] = round($data['weight'] / ($heightInMeter * $heightInMeter), 2);
        }

        return $data;
    }
}
