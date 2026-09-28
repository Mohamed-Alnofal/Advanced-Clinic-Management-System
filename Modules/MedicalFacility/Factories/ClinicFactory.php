<?php

namespace Modules\MedicalFacility\Factories;

use Modules\MedicalFacility\Models\MedicalFacility;

class ClinicFactory implements MedicalCenterFactoryInterface
{
    public function create(array $data)
    {
        $isVerified = (bool) ($data['is_verified'] ?? false);

        return MedicalFacility::create([
            'governorate_id' => $data['governorate_id'] ?? null,
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'phone' => $data['phone'] ?? null,
            'image' => $data['image'] ?? null,
            'is_verified' => $isVerified,
            'is_active' => $data['is_active'] ?? true,
            'status' => $data['status'] ?? ($isVerified ? 'approved' : 'pending'),
            'reject_reason' => $data['reject_reason'] ?? null,
        ]);
    }
}
