<?php

namespace Modules\Pharmacy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Pharmacy\Models\Pharmacy;

class PharmacyService
{
    public function create(array $data, $pharmacistId)
    {
        return DB::transaction(function () use ($data, $pharmacistId) {
            $hasPrevious = Pharmacy::where('pharmacist_id', $pharmacistId)->exists();
            $isVerified = ! $hasPrevious;

            $facility = MedicalFacility::create([
                'governorate_id' => $data['governorate_id'],
                'name' => $data['name'],
                'type' => 'pharmacy',
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_active' => true,
                // First pharmacy auto-approved; additional branches wait for admin.
                'is_verified' => $isVerified,
                'status' => $isVerified ? 'approved' : 'pending',
                'reject_reason' => null,
            ]);

            $pharmacy = Pharmacy::create([
                'pharmacist_id' => $pharmacistId,
                'medical_facility_id' => $facility->id,
            ]);

            if (! empty($data['schedules'])) {
                foreach ($data['schedules'] as $schedule) {
                    $pharmacy->schedules()->create([
                        'day' => $schedule['day'],
                        'open_time' => $schedule['open_time'] ?? null,
                        'close_time' => $schedule['close_time'] ?? null,
                        'is_closed' => $schedule['is_closed'] ?? false,
                    ]);
                }
            }

            return [
                'pharmacy' => $pharmacy->load(['medicalFacility', 'schedules']),
                'message' => $isVerified
                    ? 'Pharmacy created successfully and verified.'
                    : 'Pharmacy created successfully and is waiting for admin approval.',
                'requires_approval' => ! $isVerified,
            ];
        });
    }

    public function update($id, array $data, $pharmacistId)
    {
        $pharmacy = Pharmacy::with('medicalFacility')
            ->where('id', $id)
            ->where('pharmacist_id', $pharmacistId)
            ->firstOrFail();

        $facility = $pharmacy->medicalFacility;
        if (! $facility) {
            throw ValidationException::withMessages([
                'pharmacy' => 'Pharmacy facility not found.',
            ]);
        }

        $wasRejected = $facility->status === 'rejected';

        $payload = [
            'name' => $data['name'] ?? $facility->name,
            'address' => $data['address'] ?? $facility->address,
            'phone' => $data['phone'] ?? $facility->phone,
            'latitude' => $data['latitude'] ?? $facility->latitude,
            'longitude' => $data['longitude'] ?? $facility->longitude,
        ];

        if ($wasRejected) {
            $payload['status'] = 'pending';
            $payload['is_verified'] = false;
            $payload['reject_reason'] = null;
        }

        $facility->update($payload);

        if (! empty($data['schedules'])) {
            $pharmacy->schedules()->delete();
            foreach ($data['schedules'] as $schedule) {
                $pharmacy->schedules()->create([
                    'day' => $schedule['day'],
                    'open_time' => $schedule['open_time'] ?? null,
                    'close_time' => $schedule['close_time'] ?? null,
                    'is_closed' => $schedule['is_closed'] ?? false,
                ]);
            }
        }

        return $pharmacy->fresh(['medicalFacility', 'schedules']);
    }

    public function delete($id, $pharmacistId)
    {
        $pharmacy = Pharmacy::with('medicalFacility')
            ->where('id', $id)
            ->where('pharmacist_id', $pharmacistId)
            ->firstOrFail();

        $facility = $pharmacy->medicalFacility;
        $pharmacy->delete();
        if ($facility) {
            $facility->delete();
        }

        return true;
    }

    public function getMyPharmacies($pharmacistId)
    {
        return Pharmacy::with(['medicalFacility', 'schedules'])
            ->where('pharmacist_id', $pharmacistId)
            ->latest()
            ->get();
    }

    public function getById($id, $pharmacistId)
    {
        return Pharmacy::with([
            'medicalFacility',
            'schedules',
        ])
            ->where('id', $id)
            ->where('pharmacist_id', $pharmacistId)
            ->firstOrFail();
    }
}
