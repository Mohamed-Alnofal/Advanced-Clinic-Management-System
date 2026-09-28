<?php

namespace Modules\MedicalVisits\Services;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Models\OphthalmologyRecord;
use Modules\Patients\Models\PatientFile;

class OphthalmologyRecordService
{
    public function index()
    {
        return OphthalmologyRecord::with('medicalVisit')->get();
    }

    public function show($id)
    {
        return OphthalmologyRecord::with('medicalVisit')
            ->findOrFail($id);
    }

    public function store(array $data)
    {
        return OphthalmologyRecord::create($data);
    }

    public function update($id, array $data)
    {
        $record = OphthalmologyRecord::findOrFail($id);

        $record->update($data);

        return $record->fresh();
    }

    public function destroy($id)
    {
        $record = OphthalmologyRecord::findOrFail($id);

        return $record->delete();
    }
}