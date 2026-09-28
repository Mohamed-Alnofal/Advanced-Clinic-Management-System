<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Clinics\Models\Clinic;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Services\DoctorHolidayService;

class DoctorHolidayController extends Controller
{
    public function __construct(
        protected DoctorHolidayService $service
    ) {
    }

    public function index()
    {
        $doctor = $this->authenticatedDoctor();

        return response()->json(
            $this->service->all($doctor->id)
        );
    }

    public function store(Request $request)
    {
        $doctor = $this->authenticatedDoctor();

        $data = $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        abort_if(! $this->clinicOwnedByDoctor((int) $data['clinic_id'], (int) $doctor->id), 422);

        $data['doctor_id'] = $doctor->id;

        return response()->json(
            $this->service->create($data)
        );
    }

    public function update(Request $request, DoctorHoliday $holiday)
    {
        $doctorId = $this->authenticatedDoctor()->id;

        abort_if($holiday->doctor_id != $doctorId, 403);

        return response()->json(
            $this->service->update(
                $holiday,
                $request->validate([
                    'start_date' => 'date',
                    'end_date' => 'date|after_or_equal:start_date',
                    'reason' => 'nullable|string',
                ])
            )
        );
    }

    public function destroy(DoctorHoliday $holiday)
    {
        $doctorId = $this->authenticatedDoctor()->id;

        abort_if($holiday->doctor_id != $doctorId, 403);

        $this->service->delete($holiday);

        return response()->json([
            'message' => 'deleted',
        ]);
    }

    private function authenticatedDoctor()
    {
        $doctor = auth()->user()?->doctor;
        abort_if(! $doctor, 403);

        return $doctor;
    }

    private function clinicOwnedByDoctor(int $clinicId, int $doctorId): bool
    {
        return Clinic::query()
            ->whereKey($clinicId)
            ->where('doctor_id', $doctorId)
            ->exists();
    }
}
