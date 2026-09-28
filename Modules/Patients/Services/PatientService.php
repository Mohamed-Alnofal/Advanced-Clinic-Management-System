<?php

namespace Modules\Patients\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Http\Controllers\PatientsController;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;
use Modules\Users\Models\Relatives;
use Modules\Users\Models\User;

class PatientService
{
    public function createForUser(User $user): Patients
    {
        return Patients::firstOrCreate([
            'patientable_type' => User::class,
            'patientable_id' => $user->id
        ]);
    }

    public function createForRelative(Relatives $relative): Patients
    {
        return Patients::firstOrCreate([
            'patientable_type' => Relatives::class,
            'patientable_id' => $relative->id
        ]);
    }

    public function show(int $patientId)
    {
        return Patients::with([
            'patientable',
            'file'
        ])->findOrFail($patientId);
    }

    // public function create(Model $model)
    // {
    //     return Patients::updateOrCreate([
    //         'patientable_type' => $model::class,
    //         'patientable_id' => $model->id,
    //     ]);
    // }
    public function create(Model $model, int $doctorId)
{
    $patient = Patients::firstOrCreate([
        'patientable_type' => $model::class,
        'patientable_id' => $model->id,
    ]);

    $patientFile = PatientFile::firstOrCreate(
        [
            'patient_id' => $patient->id,
        ],
        [
            'created_by' => $doctorId,
        ]
    );

    return [
        'patient' => $patient,
        'patient_file' => $patientFile,
    ];
}


    public function getPatientWithOwner(int $patientId)
    {
        $patient = Patients::with('patientable')->findOrFail($patientId);

        // التأكد أن العلاقة تعمل فعلاً
        if (!$patient->patientable) {
            abort(404, 'Invalid patient relation');
        }

        return [
            'patient' => $patient,
            'type' => class_basename($patient->patientable_type),
            'valid' => true
        ];
    }

    //  public function searchPatients(string $search)
    // {
    //     return Patients::with('patientable')
    //         ->whereHasMorph(
    //             'patientable',
    //             [User::class, Relatives::class],
    //             function ($q) use ($search) {
    //                $q->whereRaw("LOWER(TRIM(name)) LIKE ?", [
    //             '%' . strtolower(trim($search)) . '%'
    //         ]);
    //             }
    //         )
    //         ->limit(20)
    //         ->get()
    //         ->map(function ($patient) {
    //             return [
    //                 'patient_id' => $patient->id,
    //                 'type' => class_basename($patient->patientable_type),
    //                 'id' => $patient->patientable_id,
    //                 'name' => $patient->patientable->name ?? null,
    //                 'age' => $patient->patientable->age ?? null,
    //                 'gender' => $patient->patientable->gender ?? null,
    //                 'phone' => $patient->patientable->phone ?? null,
    //             ];
    //         });
    // }
    public function searchPatients(string $search)
    {
        $search = trim($search);

        // Patients only (role: user) — never return doctors/admins/pharmacists.
        $users = User::query()
            ->role('user', 'api')
            ->where('name', 'like', "%{$search}%")
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'source' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'age' => $user->age ?? null,
                    'gender' => $user->gender ?? null,
                    'phone' => $user->phone ?? null,
                ];
            });

        $relatives = Relatives::where('name', 'like', "%{$search}%")
            ->limit(10)
            ->get()
            ->map(function ($relative) {
                return [
                    'source' => 'relative',
                    'id' => $relative->id,
                    'name' => $relative->name,
                    'age' => $relative->age ?? null,
                    'gender' => $relative->gender ?? null,
                ];
            });

        return $users->merge($relatives)->values();
    }

    public function history(int $patientId, $user = null)
    {
        $user = $user ?? auth()->user();
        /** @var \Modules\MedicalVisits\Services\MedicalVisitService $visitService */
        $visitService = app(\Modules\MedicalVisits\Services\MedicalVisitService::class);

        $query = MedicalVisits::query()
            ->whereHas('patientFile', function ($q) use ($patientId) {
                $q->where('patient_id', $patientId);
            })
            ->with([
                'doctor.user:id,name',
                'prescriptions',
                'attachments',
            ])
            ->latest();

        $isOwner = $user?->patient && (int) $user->patient->id === $patientId;
        if ($user?->doctor && ! $isOwner) {
            $visitService->scopeVisibleToDoctor($query, $user->doctor->id);
        } elseif (! $isOwner && ! $user?->doctor) {
            abort(403, 'Unauthorized.');
        }

        return $query->get();
    }
}