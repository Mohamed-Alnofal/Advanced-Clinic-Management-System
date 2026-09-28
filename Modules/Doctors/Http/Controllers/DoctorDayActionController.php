<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Services\DoctorDayActionService;

class DoctorDayActionController extends Controller
{
    public function __construct(
        protected DoctorDayActionService $service
    ) {
    }

    public function cancelDay(Request $request)
    {
        $doctor = auth()->user()?->doctor;
        abort_if(! $doctor, 403, 'Doctor profile not found');

        $data = $request->validate([
            'date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->service->cancelDay(
                $doctor->id,
                $data['date'],
                $data['reason'] ?? null
            )
        );
    }

    public function delayDay(Request $request)
    {
        $doctor = auth()->user()?->doctor;
        abort_if(! $doctor, 403, 'Doctor profile not found');

        $data = $request->validate([
            'date' => 'required|date',
            'minutes' => 'required|integer|min:1|max:720',
            'reason' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->service->delayDay(
                $doctor->id,
                $data['date'],
                $data['minutes'],
                $data['reason'] ?? null
            )
        );
    }
}
