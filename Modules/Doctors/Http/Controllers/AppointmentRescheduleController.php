<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Appointments\Models\Appointment;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Models\AppointmentReschedule;
use Modules\Doctors\Services\AppointmentRescheduleService;

class AppointmentRescheduleController extends Controller
{
    public function __construct(
        protected AppointmentRescheduleService $service
    ) {
    }

    public function store(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'new_date' => 'required|date',
            'new_time' => 'required',
            'reason' => 'nullable|string|max:500',
        ]);

        $reschedule = $this->service->requestReschedule($appointment, $data);

        return response()->json($reschedule->toApiArray());
    }

    public function accept(AppointmentReschedule $reschedule)
    {
        return response()->json($this->service->accept($reschedule));
    }

    public function reject(AppointmentReschedule $reschedule)
    {
        return response()->json($this->service->reject($reschedule));
    }
}
