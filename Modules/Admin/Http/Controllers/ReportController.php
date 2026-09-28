<?php

namespace Modules\Admin\Http\Controllers;
use Modules\Core\Http\Controllers\Controller;

use Modules\Doctors\Models\Doctor;
use Modules\Appointments\Models\Appointment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // 1. تقرير الأطباء الجدد
    public function newDoctors()
    {
        $doctors = Doctor::with(['user', 'certificates'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($doctors);
    }

    // 2. تقرير المواعيد الملغاة
    public function cancelledAppointments()
    {
        $appointments = Appointment::with(['doctor.user', 'user'])
            ->where('status', 'cancelled')
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return response()->json($appointments);
    }

 
}