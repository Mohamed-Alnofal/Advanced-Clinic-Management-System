<?php

namespace Modules\Clinics\Services;

use Modules\Clinics\Models\Clinic;
use Modules\MedicalFacility\Factories\MedicalCenterFactory;
use Modules\MedicalFacility\Models\MedicalFacility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClinicService
{
    public function create($user, array $data)
{
    if (! $user->hasRole('doctor') || ! $user->doctor) {
        throw ValidationException::withMessages([
            'role' => 'Only doctors can create clinics.',
        ]);
    }

    return DB::transaction(function () use ($user, $data) {

        // Lock doctor row to prevent concurrent first-clinic creation
        $doctor = $user->doctor()
            ->lockForUpdate()
            ->first();

        $name = (string) ($data['name'] ?? '');
        $address = (string) ($data['address'] ?? '');
        $governorateId = isset($data['governorate_id'])
            ? (int) $data['governorate_id']
            : null;

        $this->assertUniqueNameAddressGovernorate(
            name: $name,
            address: $address,
            governorateId: $governorateId,
        );

        /*
         * Check if doctor already has a clinic.
         *
         * First clinic  => automatically verified
         * Other clinics => waiting for admin approval
         */
        $hasPreviousClinic = Clinic::where('doctor_id', $doctor->id)
            ->exists();

        $isVerified = ! $hasPreviousClinic;

        $factory = MedicalCenterFactory::make('clinic');

        $imagePath = null;

        if (
            isset($data['image']) &&
            $data['image'] &&
            $data['image']->isValid()
        ) {
            $imagePath = $data['image']->store(
                'medical_centers',
                'public'
            );
        }

        $medicalCenter = $factory->create([
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'governorate_id' => $data['governorate_id'],
            'phone' => $data['phone'] ?? null,
            'image' => $imagePath,
            'type' => 'clinic',
            // First clinic auto-approved; additional branches wait for admin.
            'is_verified' => $isVerified,
            'status' => $isVerified ? 'approved' : 'pending',
            'reject_reason' => null,
        ]);

        $clinic = Clinic::create([
            'medical_facility_id' => $medicalCenter->id,
            'doctor_id' => $doctor->id,
        ]);

        return [
            'clinic' => $clinic->load('medicalCenter'),
            'message' => $isVerified
                ? 'Clinic created successfully and verified.'
                : 'Clinic created successfully and is waiting for admin approval.',
        ];
    });
}
    // public function create($user, array $data)
    // {
    //     if (! $user->hasRole('doctor') || ! $user->doctor) {
    //         throw ValidationException::withMessages([
    //             'role' => 'Only doctors can create clinics.',
    //         ]);
    //     }

    //     $doctor = $user->doctor;
    //     $name = (string) ($data['name'] ?? '');
    //     $address = (string) ($data['address'] ?? '');
    //     $governorateId = isset($data['governorate_id']) ? (int) $data['governorate_id'] : null;

    //     $this->assertUniqueNameAddressGovernorate(
    //         name: $name,
    //         address: $address,
    //         governorateId: $governorateId,
    //     );

    //     return DB::transaction(function () use ($doctor, $data) {
    //         $factory = MedicalCenterFactory::make('clinic');

    //         $imagePath = null;
    //         if (isset($data['image']) && $data['image'] && $data['image']->isValid()) {
    //             $imagePath = $data['image']->store('medical_centers', 'public');
    //         }

    //         $medicalCenter = $factory->create([
    //             'name' => $data['name'],
    //             'address' => $data['address'],
    //             'latitude' => $data['latitude'] ?? null,
    //             'longitude' => $data['longitude'] ?? null,
    //             'governorate_id' => $data['governorate_id'],
    //             'phone' => $data['phone'] ?? null,
    //             'image' => $imagePath,
    //             'type' => 'clinic',
    //         ]);

    //         $clinic = Clinic::create([
    //             'medical_facility_id' => $medicalCenter->id,
    //             'doctor_id' => $doctor->id,
    //         ]);

    //         return [
    //             'clinic' => $clinic->load('medicalCenter'),
    //             'message' => 'Clinic created successfully.',
    //         ];
    //     });
    // }

    public function update($user, $clinicId, $data)
    {
        if (! $user->hasRole('doctor') || ! $user->doctor) {
            throw ValidationException::withMessages([
                'role' => 'Only doctors can update clinics.',
            ]);
        }

        $doctor = $user->doctor;
        $clinic = $doctor->clinics()
            ->with('medicalCenter')
            ->where('id', $clinicId)
            ->first();

        if (! $clinic || ! $clinic->medicalCenter) {
            throw ValidationException::withMessages([
                'clinic' => 'Clinic not found.',
            ]);
        }

        $medicalCenter = $clinic->medicalCenter;
        $name = (string) ($data['name'] ?? $medicalCenter->name);
        $address = (string) ($data['address'] ?? $medicalCenter->address);
        $governorateId = isset($data['governorate_id'])
            ? (int) $data['governorate_id']
            : (int) $medicalCenter->governorate_id;

        $this->assertUniqueNameAddressGovernorate(
            name: $name,
            address: $address,
            governorateId: $governorateId,
            exceptFacilityId: (int) $medicalCenter->id,
        );

        if (isset($data['image']) && $data['image'] && $data['image']->isValid()) {
            $raw = $medicalCenter->getRawOriginal('image');
            if ($raw) {
                Storage::disk('public')->delete($raw);
            }
            $data['image'] = $data['image']->store('medical_centers', 'public');
        } else {
            unset($data['image']);
        }

        $wasRejected = ($medicalCenter->status ?? null) === 'rejected';

        $payload = [
            'name' => $name,
            'address' => $address,
            'latitude' => $data['latitude'] ?? $medicalCenter->latitude,
            'longitude' => $data['longitude'] ?? $medicalCenter->longitude,
            'governorate_id' => $governorateId,
            'phone' => $data['phone'] ?? $medicalCenter->phone,
            'image' => $data['image'] ?? $medicalCenter->getRawOriginal('image'),
            'type' => $medicalCenter->type,
        ];

        // After admin rejection, doctor edits re-open the verification queue.
        if ($wasRejected) {
            $payload['status'] = 'pending';
            $payload['is_verified'] = false;
            $payload['reject_reason'] = null;
        }

        $medicalCenter->update($payload);

        return $clinic->fresh()->load('medicalCenter');
    }

    public function getDoctorClinics($user)
    {
        if (! $user->doctor) {
            throw ValidationException::withMessages([
                'role' => 'Only doctors can view clinics.',
            ]);
        }

        return Clinic::with(['medicalCenter.governorate'])
            ->where('doctor_id', $user->doctor->id)
            ->latest()
            ->get();
    }

    public function delete($user, $clinicId)
    {
        if (! $user->hasRole('doctor') || ! $user->doctor) {
            throw ValidationException::withMessages([
                'role' => 'Only doctors can delete clinics.',
            ]);
        }

        $doctor = $user->doctor;
        $clinic = $doctor->clinics()
            ->with('medicalCenter')
            ->where('id', $clinicId)
            ->first();

        if (! $clinic) {
            throw ValidationException::withMessages([
                'clinic' => 'Clinic not found.',
            ]);
        }

        $activeAppointments = \Modules\Appointments\Models\Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotIn('status', ['rejected', 'cancelled', 'completed'])
            ->exists();

        if ($activeAppointments) {
            throw ValidationException::withMessages([
                'clinic' => 'Cannot delete this clinic while it has active appointments.',
            ]);
        }

        $bookedSlots = \Modules\Doctors\Models\DoctorSchedule::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_booked', true)
            ->exists();

        if ($bookedSlots) {
            throw ValidationException::withMessages([
                'clinic' => 'Cannot delete this clinic while it has booked time slots.',
            ]);
        }

        return DB::transaction(function () use ($clinic) {
            $medicalCenter = $clinic->medicalCenter;
            $facilityId = $clinic->medical_facility_id;

            // Remove free clinic slots before deleting the clinic row.
            \Modules\Doctors\Models\DoctorSchedule::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_booked', false)
                ->delete();

            $clinic->delete();

            $remaining = Clinic::where('medical_facility_id', $facilityId)->count();
            if ($remaining === 0 && $medicalCenter) {
                $raw = $medicalCenter->getRawOriginal('image');
                if ($raw) {
                    Storage::disk('public')->delete($raw);
                }
                $medicalCenter->delete();
            }
        });
    }

    /**
     * Prevent duplicate clinics: same name + address + governorate.
     */
    private function assertUniqueNameAddressGovernorate(
        string $name,
        string $address,
        ?int $governorateId,
        ?int $exceptFacilityId = null,
    ): void {
        if ($governorateId === null || trim($name) === '' || trim($address) === '') {
            return;
        }

        $nameKey = $this->normalize($name);
        $addressKey = $this->normalize($address);

        $query = MedicalFacility::query()
            ->where('type', 'clinic')
            ->where('governorate_id', $governorateId);

        if ($exceptFacilityId) {
            $query->where('id', '!=', $exceptFacilityId);
        }

        foreach ($query->get(['id', 'name', 'address']) as $facility) {
            if (
                $this->normalize((string) $facility->name) === $nameKey
                && $this->normalize((string) $facility->address) === $addressKey
            ) {
                throw ValidationException::withMessages([
                    'name' => 'A clinic with the same name, address, and governorate already exists. Please choose a different name or location.',
                ]);
            }
        }
    }

    private function normalize(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return $v;
    }

    /**
     * Get pending medical facility requests (clinics / pharmacies / all).
     */
    public function getPendingRequests(int $perPage = 20, ?string $type = null)
    {
        $query = MedicalFacility::with([
            'governorate',
            'clinic.doctor.user',
            'pharmacy.pharmacist.user',
        ])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('is_verified', false)
                    ->orWhereNull('is_verified');
            });

        if (in_array($type, ['clinic', 'pharmacy', 'lab', 'hospital', 'ambulance'], true)) {
            $query->where('type', $type);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Approve a pending medical facility.
     */
    public function approve(int $facilityId): MedicalFacility
    {
        $facility = MedicalFacility::findOrFail($facilityId);

        if ($facility->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $facility->update([
            'status' => 'approved',
            'is_verified' => true,
            'reject_reason' => null,
        ]);

        return $facility->fresh([
            'governorate',
        ]);
    }

    /**
     * Reject a pending medical facility.
     */
    public function reject(
        int $facilityId,
        string $reason
    ): MedicalFacility {

        $facility = MedicalFacility::findOrFail($facilityId);

        if ($facility->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $facility->update([
            'status' => 'rejected',
            'is_verified' => false,
            'reject_reason' => $reason,
        ]);

        return $facility->fresh([
            'governorate',
        ]);
    }
}
