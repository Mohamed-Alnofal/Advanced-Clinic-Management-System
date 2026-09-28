<?php

namespace Modules\Appointments\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Appointments\Services\AppointmentService;
use Modules\Appointments\Services\PatientAppointmentService;
use Modules\Appointments\Services\DoctorAppointmentService;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    private $patientService;
    private $doctorService;

    public function __construct(
        PatientAppointmentService $patientService,
        DoctorAppointmentService $doctorService
    ) {
        // Keep write-side AppointmentService lazy so list endpoints do not
        // fail to boot when Payment/Stripe dependencies are misconfigured.
        $this->patientService = $patientService;
        $this->doctorService = $doctorService;
    }

    private function appointmentService(): AppointmentService
    {
        return app(AppointmentService::class);
    }

    private function requireUser()
    {
        $user = auth()->user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    // =============================================
    // 📅 عمليات الحجز والإلغاء (للمريض)
    // =============================================

    public function book(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'nullable|exists:clinics,id|required_if:appointment_type,clinic',
            'schedule_id' => 'required|exists:doctor_schedules,id',
            'booking_for' => 'nullable|in:self,relative',
            'name' => 'required_if:booking_for,relative',
            'age' => 'required_if:booking_for,relative',
            'gender' => 'required_if:booking_for,relative',
            'appointment_type' => 'nullable|in:clinic,video',
            'appointment_date' => 'nullable|date',
            'appointment_time' => 'nullable|date',
            'use_wallet_escrow' => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:wallet,stripe,pay_later,pay_at_clinic',
        ]);

        try {
            $result = $this->appointmentService()->book($request->all(), $this->requireUser());

            if (is_array($result) && ($result['status'] ?? true) === false) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Could not book the appointment.',
                'code' => 'booking_failed',
            ], 422);
        }
    }

public function pay(Request $request)
{
    $request->validate([
        'appointment_id' => 'required|exists:appointments,id',
        'payment_method' => 'required|string|in:wallet,stripe,pay_later',
    ]);

    try {
        $result = $this->appointmentService()->payForAppointment(
            $request->all(),
            $this->requireUser()
        );

        $ok = ($result['status'] ?? false) === true;

        return response()->json($result, $ok ? 200 : 422);
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status' => false,
            'message' => $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Payment could not be completed. Please try again.',
            'code' => 'payment_failed',
        ], 422);
    }
}
    /**
 * ⚠️ دالة للتوافق مع الإصدارات القديمة (DEPRECATED)
 * استخدم doctorAccept, doctorReject, doctorComplete بدلاً من ذلك
 */
public function action(Request $request)
{
    $request->validate([
        'appointment_id' => 'required|exists:appointments,id',
        'status' => 'required|in:accepted,rejected,cancelled,pending,completed',
        'reason' => 'nullable|string'
    ]);

    $doctor = $this->requireUser()->doctor;
    
    if (!$doctor) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    $result = $this->appointmentService()->doctorAction(
        $request->appointment_id,
        $doctor,
        $request->status,
        $request->reason
    );
    
    return response()->json($result);
}
    public function reschedule(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'new_schedule_id' => 'required|exists:doctor_schedules,id',
        ]);

        return response()->json(
            $this->appointmentService()->reschedule($request->all(), $this->requireUser())
        );
    }
    
    public function cancel(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:doctor_schedules,id'
        ]);

        return response()->json(
            $this->appointmentService()->cancelAppointment($request->schedule_id, $this->requireUser())
        );
    }

    public function delete(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id'
        ]);

        return response()->json(
            $this->appointmentService()->deleteAppointment($request->appointment_id, $this->requireUser())
        );
    }

    // =============================================
    // 👤 دوال للمريض (Patient)
    // =============================================

    /**
     * عرض جميع مواعيد المريض
     */
    public function myAppointments()
    {
        try {
            $appointments = $this->patientService->getAllAppointments($this->requireUser());

            return response()->json([
                'success' => true,
                'data' => $appointments
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not load appointments.',
            ], 500);
        }
    }

    /**
     * عرض المواعيد القادمة للمريض
     */
    public function myUpcomingAppointments()
    {
        $appointments = $this->patientService->getUpcomingAppointments($this->requireUser());
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

    /**
     * عرض استشارات الفيديو للمريض
     */
    public function myVideoAppointments()
    {
        $appointments = $this->patientService->getVideoAppointments($this->requireUser());
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

    /**
     * التحقق من المواعيد القريبة (للتذكير)
     */
    public function checkNearbyAppointments()
    {
        $appointments = $this->patientService->checkNearbyAppointments($this->requireUser());
        
        return response()->json([
            'success' => true,
            'has_upcoming' => $appointments->isNotEmpty(),
            'data' => $appointments
        ]);
    }

    // =============================================
    // 🩺 دوال للطبيب (Doctor)
    // =============================================

    /**
     * عرض طلبات المواعيد المعلقة للطبيب
     */
    public function doctorPendingRequests(Request $request)
    {
        $doctor = $this->requireUser()->doctor;
        
        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $clinicId = $request->integer('clinic_id') ?: null;
        $appointments = $this->doctorService->getPendingAppointments(
            $doctor->id,
            $clinicId > 0 ? $clinicId : null
        );
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

    /**
     * عرض المواعيد القادمة للطبيب
     */
    public function doctorUpcomingAppointments(Request $request)
    {
        $doctor = $this->requireUser()->doctor;
        
        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $clinicId = $request->integer('clinic_id') ?: null;
        $appointments = $this->doctorService->getUpcomingAppointments(
            $doctor->id,
            $clinicId > 0 ? $clinicId : null
        );
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

    /**
     * عرض جميع مواعيد الطبيب
     */
    public function doctorAllAppointments(Request $request)
    {
        $doctor = $this->requireUser()->doctor;
        
        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $status = $request->get('status');
        $clinicId = $request->integer('clinic_id') ?: null;
        $appointments = $this->doctorService->getAllAppointments(
            $doctor->id,
            $status,
            $clinicId > 0 ? $clinicId : null
        );
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

    /**
     * عرض طلبات الفيديو المعلقة للطبيب
     */
    public function doctorVideoPendingRequests()
    {
        $doctor = $this->requireUser()->doctor;
        
        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $appointments = $this->doctorService->getVideoPendingAppointments($doctor->id);
        
        return response()->json([
            'success' => true,
            'data' => $appointments
        ]);
    }

 
  
}