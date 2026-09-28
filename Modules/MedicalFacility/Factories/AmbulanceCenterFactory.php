<?php

namespace Modules\MedicalFacility\Factories;

use Modules\AmbulanceCenter\Models\AmbulanceCenter;

class AmbulanceCenterFactory implements MedicalCenterFactoryInterface
{
    public function create(array $data)
    {
        return AmbulanceCenter::create([
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'ambulance_count' => $data['ambulance_count'],
        ]);
    }
}