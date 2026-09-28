<?php

namespace Modules\Patients\Services;

use Modules\Patients\Models\PatientFile;

class PatientFileService
{
    /**
     * Ensure a single unified chart per patient.
     */
    public function create(array $data): PatientFile
    {
        $doctorId = auth()->user()->doctor->id;

        return PatientFile::firstOrCreate(
            ['patient_id' => $data['patient_id']],
            ['created_by' => $doctorId]
        );
    }

    public function show($id)
    {
        return PatientFile::with('medicalVisits')->findOrFail($id);
    }
}
