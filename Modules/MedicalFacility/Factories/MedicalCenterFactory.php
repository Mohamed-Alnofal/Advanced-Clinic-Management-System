<?php

namespace Modules\MedicalFacility\Factories;

class MedicalCenterFactory
{
    public static function make($type)
    {
        return match ($type) {
            'clinic' => new ClinicFactory(),
            'pharmacy' => new PharmacyFactory(),
            'ambulance' => new AmbulanceCenterFactory(),
            default => throw new \Exception("Invalid type"),
        };
    }
}