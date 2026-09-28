<?php

namespace Modules\Core\Services;

use Modules\Core\Models\specializations;

class SpecializationService
{
    public function getAll()
    {
        return specializations::latest()->get();
    }

    public function getById(int $id)
    {
        return specializations::findOrFail($id);
    }

    public function create(array $data)
    {
        return specializations::create($data);
    }

    public function update(int $id, array $data)
    {
        $specialization = specializations::findOrFail($id);

        $specialization->update($data);

        return $specialization->fresh();
    }

    public function delete(int $id): bool
    {
        $specialization = specializations::findOrFail($id);

        return $specialization->delete();
    }
}