<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Str;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Ambulance\Models\AmbulanceDriver;
use Modules\Ambulance\Models\AmbulanceCompany;

class AmbulanceUnitService
{
    public function index($companyId)
    {
        return AmbulanceUnit::with('driver')
            ->where('ambulance_company_id', $companyId)
            ->get();
    }

    public function store($companyId, array $data)
    {
        // التحقق من وجود الشركة
        if (!AmbulanceCompany::where('id', $companyId)->exists()) {
            return [
                'success' => false,
                'message' => 'Ambulance company not found.'
            ];
        }

        // إذا تم اختيار سائق
        if (!empty($data['ambulance_driver_id'])) {

            $driver = AmbulanceDriver::find($data['ambulance_driver_id']);

            if (!$driver) {
                return [
                    'success' => false,
                    'message' => 'Driver not found.'
                ];
            }

            // السائق يجب أن يتبع نفس الشركة
            if ($driver->ambulance_company_id != $companyId) {
                return [
                    'success' => false,
                    'message' => 'The selected driver does not belong to your company.'
                ];
            }

            // السائق لا يمكن أن يكون مربوطاً بسيارة أخرى
            if (AmbulanceUnit::where('ambulance_driver_id', $driver->id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'This driver is already assigned to another ambulance.'
                ];
            }
        }

        $unit = AmbulanceUnit::create([
            'ambulance_company_id' => $companyId,
            'ambulance_driver_id' => $data['ambulance_driver_id'] ?? null,
            'plate_number' => $data['plate_number'],
            'status' => 'offline',
        ]);

        return [
            'success' => true,
            'message' => 'Ambulance created successfully.',
            'data' => $unit
        ];
    }

    public function update(AmbulanceUnit $unit, array $data)
    {
        // التحقق من الشركة إذا تم تغييرها
        if (isset($data['ambulance_company_id'])) {

            if (!AmbulanceCompany::where('id', $data['ambulance_company_id'])->exists()) {
                return [
                    'success' => false,
                    'message' => 'Ambulance company not found.'
                ];
            }
        }

        // إذا تم تغيير السائق
        if (!empty($data['ambulance_driver_id'])) {

            $driver = AmbulanceDriver::find($data['ambulance_driver_id']);

            if (!$driver) {
                return [
                    'success' => false,
                    'message' => 'Driver not found.'
                ];
            }

            // السائق يجب أن يتبع نفس الشركة
            if ($driver->ambulance_company_id != $unit->ambulance_company_id) {
                return [
                    'success' => false,
                    'message' => 'The selected driver does not belong to this company.'
                ];
            }

            // السائق لا يمكن أن يكون مربوطاً بسيارة أخرى
            $assigned = AmbulanceUnit::where('ambulance_driver_id', $driver->id)
                ->where('id', '!=', $unit->id)
                ->exists();

            if ($assigned) {
                return [
                    'success' => false,
                    'message' => 'This driver is already assigned to another ambulance.'
                ];
            }
        }

        $unit->update($data);

        return [
            'success' => true,
            'message' => 'Ambulance updated successfully.',
            'data' => $unit->fresh()
        ];
    }

    public function updateLocation(AmbulanceUnit $unit, array $data)
    {
        $unit->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'last_location_update' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Location updated successfully.',
            'data' => $unit->fresh()
        ];
    }

    public function updateStatus(AmbulanceUnit $unit, string $status)
    {
        $unit->update([
            'status' => $status
        ]);

        return [
            'success' => true,
            'message' => 'Status updated successfully.',
            'data' => $unit->fresh()
        ];
    }

    public function destroy(AmbulanceUnit $unit)
    {
        $unit->delete();

        return [
            'success' => true,
            'message' => 'Ambulance deleted successfully.'
        ];
    }

  
}