<?php

namespace Modules\MedicalVisits\Services;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Models\OcularHistory;

class OcularHistoryService
{
    public function index()
    {
        return OcularHistory::with('patientFile')
            ->latest()
            ->get();
    }

   public function show(int $id)
    {
        return OcularHistory::with('patientFile')
            ->findOrFail($id);
    }
    public function store(array $data)
    {
        return OcularHistory::create($data);
    }

    public function update(
        int $id,
        array $data
    )
    {
        $history = OcularHistory::findOrFail($id);

        $history->update($data);

        return $history->fresh();
    }

    public function destroy(int $id)
    {
        $history = OcularHistory::findOrFail($id);

        return $history->delete();
    }
}