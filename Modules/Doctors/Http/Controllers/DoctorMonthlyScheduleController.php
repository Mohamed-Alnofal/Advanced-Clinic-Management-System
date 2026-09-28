<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Clinics\Models\Clinic;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Services\DoctorMonthlyScheduleService;

class DoctorMonthlyScheduleController extends Controller
{
    public function __construct(
        protected DoctorMonthlyScheduleService $service
    ) {
    }

    public function generate(Request $request)
    {
        $doctor = $this->authenticatedDoctor();

        $data = $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'month' => 'required|date_format:Y-m',
        ]);

        abort_if(
            ! Clinic::query()
                ->whereKey($data['clinic_id'])
                ->where('doctor_id', $doctor->id)
                ->exists(),
            422
        );

        return response()->json(
            $this->service->generate(
                $doctor->id,
                $data['clinic_id'],
                $data['month']
            )
        );
    }

    public function index(Request $request)
    {
        $doctor = $this->authenticatedDoctor();

        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        return response()->json(
            $this->service->getMonth(
                $doctor->id,
                $data['month']
            )
        );
    }

    public function patientCalendar(Request $request, $doctorId)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'clinic_id' => 'nullable|integer|exists:clinics,id',
        ]);

        return response()->json(
            $this->service->patientCalendar(
                $doctorId,
                $data['month'],
                isset($data['clinic_id']) ? (int) $data['clinic_id'] : null
            )
        );
    }

    public function availableSlots(Request $request, $doctorId)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'type' => 'nullable|string',
            'clinic_id' => 'nullable|integer|exists:clinics,id',
        ]);

        return response()->json(
            $this->service->availableSlots(
                $doctorId,
                $data['date'],
                $data['type'] ?? null,
                isset($data['clinic_id']) ? (int) $data['clinic_id'] : null
            )
        );
    }

    public function monthlyAvailableSlots(Request $request, $doctorId)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        return response()->json(
            $this->service->monthlyAvailableSlots(
                $doctorId,
                $data['month']
            )
        );
    }

    private function authenticatedDoctor()
    {
        $doctor = auth()->user()?->doctor;
        abort_if(! $doctor, 403);

        return $doctor;
    }
}
