<?php

namespace Modules\Clinics\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClinicResource extends JsonResource
{
    public function toArray($request)
    {
        $center = $this->medicalCenter;

        if (! $center) {
            return [
                'id' => $this->id,
                'medical_facility_id' => $this->medical_facility_id,
                'medical_center' => null,
            ];
        }

        return [
            'id' => $this->id,
            'medical_facility_id' => $this->medical_facility_id,
            'medical_center' => [
                'id' => $center->id,
                'name' => $center->name,
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
                'address' => $center->address,
                'governorate_id' => $center->governorate_id,
                'type' => $center->type,
                'phone' => $center->phone,
                'image' => $center->image,
                'status' => $center->status ?? 'pending',
                'is_verified' => (bool) $center->is_verified,
                'is_active' => (bool) $center->is_active,
                'reject_reason' => $center->reject_reason,
            ],
        ];
    }
}
