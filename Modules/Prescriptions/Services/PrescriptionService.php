<?php

namespace Modules\Prescriptions\Services;

// use Modules\Prescriptions\Models\Prescriptions;
use Illuminate\Support\Facades\DB;
use Modules\Prescriptions\Models\Prescriptions;

class PrescriptionService
{

    public function index()
    {
        return Prescriptions::with([
            'items',
            'visit'
        ])->latest()->paginate(10);
    }

    public function show(int $id): Prescriptions
    {
        return Prescriptions::with([
            'items',
            'visit'
        ])->findOrFail($id);
    }
    public function create(array $data): Prescriptions
    {
        return DB::transaction(function () use ($data) {

            $prescription = Prescriptions::create([
                'medical_visit_id' => $data['medical_visit_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            $prescription->items()->createMany(
                $data['items']
            );

            return $prescription->load('items');
        });
    }
    public function update(int $id, array $data)
    {
        $prescription = Prescriptions::with('visit')->findOrFail($id);

        $doctorId = auth()->user()->doctor->id;

        if ($prescription->visit->doctor_id !== $doctorId) {
            abort(403, 'You are not allowed to update this prescription');
        }

        $prescription->update([
            'notes' => $data['notes'] ?? null,
        ]);

        $prescription->items()->delete();
        $prescription->items()->createMany($data['items']);

        return $prescription->fresh()->load('items');
    }

    public function patientPrescriptions(
    int $patientId
    )
    {
        return Prescriptions::with([
            'items',
            'visit'
        ])
        ->whereHas(
            'visit.patientFile',
            function ($query) use ($patientId) {

                $query->where(
                    'patient_id',
                    $patientId
                );
            }
        )
        ->latest()
        ->get();
    }

    public function destroy(int $id): bool
    {
        $prescription = Prescriptions::with('visit')->findOrFail($id);

        $doctorId = auth()->user()->doctor->id;

        if ($prescription->visit->doctor_id !== $doctorId) {
            abort(403, 'You are not allowed to delete this prescription');
        }

        return $prescription->delete();
    }


    public function myPrescriptions()
    {
        $user = auth()->user();

        return Prescriptions::with([
            'items',
            'visit.doctor',
        ])
        ->whereHas(
            'visit.patientFile.patient',
            function ($query) use ($user) {

                $query->where(
                    'patientable_type',
                    get_class($user)
                )
                ->where(
                    'patientable_id',
                    $user->id
                );

            }
        )
        ->latest()
        ->paginate(10);
    }
    

    
}
