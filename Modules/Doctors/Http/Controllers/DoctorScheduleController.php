<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Core\Http\Requests\StoreDoctorScheduleRequest as RequestsStoreDoctorScheduleRequest;
use Modules\Core\Http\StoreDoctorScheduleRequest;
// use Modules\Core\Http\StoreDoctorScheduleRequest;
use Modules\Doctors\Services\DoctorScheduleService;
// use App\Http\Requests\StoreDoctorScheduleRequest;
  // use App\Services\DoctorScheduleService;
class DoctorScheduleController extends Controller
{
    //
        private $service;

    public function __construct(DoctorScheduleService $service)
    {
        $this->service = $service;
    }
 // إنشاء أو تحديث الجدول
    public function store(RequestsStoreDoctorScheduleRequest $request)
    {
        $doctor = auth()->user()->doctor;

        $result = $this->service->storeOrUpdate(
            $request->validated(),
            $doctor
        );

        return response()->json($result);
    }

    public function mySchedule()
    {
        $doctor = auth()->user()->doctor;

        return response()->json(
            $this->service->getMySchedule($doctor)
        );
    }
    // public function show($doctorId)
    // {
    //     return response()->json(
    //         $this->service->getSchedule($doctorId)
    //     );
    // }

        public function show(Request $request, $doctorId)
        {
            return response()->json([
                'success' => true,
                'doctor_id' => (int) $doctorId,
                'schedules' => $this->service->getSchedule(
                    $doctorId,
                    $request->query('date')
                ),
            ]);
        }

    public function showSlot($scheduleId)
    {
        $slot = $this->service->getSlotById((int) $scheduleId);
        if (! $slot) {
            return response()->json(['message' => 'Schedule slot not found'], 404);
        }

        return response()->json(['data' => $slot]);
    }


    public function destroySchedules()
    {
        $doctor = auth()->user()->doctor;

        $result = $this->service->deleteDoctorSchedules($doctor);

        return response()->json($result);
    }
    
}
