<?php

namespace Modules\Consultation\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Modules\Consultation\Models\Consultation;
use Modules\Consultation\Models\VideoAudit;
use Modules\Consultation\Http\Requests\SaveConsultationNotesRequest;
use Modules\Consultation\Services\ConsultationNotesService;
use Modules\Consultation\Services\ConsultationJoinPolicy;
use Modules\Consultation\Services\TokenGeneratorService;
use Modules\Appointments\Models\Appointment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ConsultationController extends Controller
{
    protected TokenGeneratorService $tokenService;
    protected ConsultationNotesService $notesService;

    public function __construct(TokenGeneratorService $tokenService, ConsultationNotesService $notesService)
    {
        $this->tokenService = $tokenService;
        $this->notesService = $notesService;
    }

    /**
     * إنشاء أو بدء الاستشارة
     */
public function start($appointmentId)
{
    $appointment = Appointment::with([
        'doctor',
        'patient',
        'schedule'
    ])->findOrFail($appointmentId);

    $doctorUserId = $appointment->doctor?->user_id;

    if (
        auth()->id() !== $doctorUserId &&
        auth()->id() !== $appointment->user_id
    ) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    if ($appointment->appointment_type !== 'video') {
        return response()->json([
            'message' => 'This appointment is not a video consultation'
        ], 400);
    }

    if (!in_array($appointment->status, ['accepted'], true)) {
        return response()->json([
            'message' => 'Appointment must be accepted before starting the video call',
        ], 422);
    }

    $existing = Consultation::where('appointment_id', $appointment->id)->first();
    $denied = ConsultationJoinPolicy::denyJoinResponse($appointment, $existing, auth()->user());
    if ($denied) {
        return $denied;
    }

    $consultation = Consultation::firstOrCreate(
        ['appointment_id' => $appointment->id],
        [
            'room_name' => Consultation::generateRoomName(),
            'status' => 'scheduled'
        ]
    );

    $this->logVideoAudit($consultation, 'consultation_started', [
        'status' => $consultation->status,
        'was_recently_created' => $consultation->wasRecentlyCreated,
    ]);

    return response()->json([
        'success' => true,
        'data' => [
            'consultation_id' => $consultation->id,
            'room_name' => $consultation->room_name,
            'status' => $consultation->status
        ]
    ]);
}

    /**
     * الحصول على Token
     */
 /**
 * توليد Token للدخول إلى المكالمة
 */
public function getToken($appointmentId)
{
    $consultation = Consultation::with(['appointment.doctor', 'appointment.schedule'])
        ->where('appointment_id', $appointmentId)
        ->firstOrFail();

    $appointment = $consultation->appointment;
    $user = auth()->user();
    $doctorUserId = $appointment->doctor?->user_id;

    if ($user->id !== $doctorUserId && $user->id !== $appointment->user_id) {
        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to join this call',
        ], 403);
    }

    if ($appointment->appointment_type !== 'video') {
        return response()->json([
            'success' => false,
            'message' => 'This appointment is not a video consultation',
        ], 400);
    }

    if ($appointment->status !== 'accepted') {
        return response()->json([
            'success' => false,
            'message' => 'Appointment must be accepted before joining the video call',
        ], 422);
    }

    $denied = ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $user);
    if ($denied) {
        return $denied;
    }

    $window = ConsultationJoinPolicy::slotWindow($appointment);

    try {
        $tokenData = $this->tokenService->generate(
            $consultation,
            $user,
            ConsultationJoinPolicy::livekitTtlSeconds($appointment)
        );
        $tokenData['scheduled_duration_minutes'] = is_array($window) ? ($window['duration'] ?? 30) : 30;
        $tokenData['slot_ends_at'] = is_array($window) ? $window['end']->toIso8601String() : null;
        $tokenData['started_at'] = optional($consultation->started_at)?->toIso8601String();
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Could not generate video call token. Check LiveKit configuration.',
        ], 500);
    }

    $this->logVideoAudit($consultation, 'token_generated', [
        'room_name' => $consultation->room_name,
        'user_name' => $user->name,
        'token_role' => $tokenData['role'] ?? null,
    ]);

    return response()->json(['success' => true, 'data' => $tokenData]);
}

    /**
     * تسجيل دخول المستخدم
     */
  public function join($appointmentId)
{
    $consultation = Consultation::with([
        'appointment.doctor',
        'appointment.schedule',
    ])->where('appointment_id', $appointmentId)
      ->firstOrFail();

    $appointment = $consultation->appointment;
    $doctorUserId = $appointment->doctor?->user_id;

    if (
        auth()->id() !== $doctorUserId &&
        auth()->id() !== $appointment->user_id
    ) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    $denied = ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, auth()->user());
    if ($denied) {
        return $denied;
    }

    if (!$consultation->started_at) {
        $consultation->update([
            'started_at' => now(),
            'status' => 'active'
        ]);
    }

    $this->logVideoAudit($consultation, 'participant_joined', [
        'started_at' => optional($consultation->started_at)->toDateTimeString(),
        'status' => $consultation->status,
    ]);

    return response()->json([
        'success' => true,
        'started_at' => $consultation->started_at
    ]);
}

    /**
     * إنهاء المكالمة
     */
public function end($appointmentId)
{
    $consultation = Consultation::with([
        'appointment.doctor'
    ])->where('appointment_id', $appointmentId)
      ->firstOrFail();

    $appointment = $consultation->appointment;
    $doctorUserId = $appointment->doctor?->user_id;
    $userId = auth()->id();

    if ($userId !== $doctorUserId && $userId !== $appointment->user_id) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    if ($consultation->status === 'completed') {
        return response()->json([
            'success' => true,
            'message' => 'Consultation already completed',
            'duration_seconds' => 0,
            'duration_formatted' => '00:00:00',
        ]);
    }

    $duration = $consultation->started_at
        ? $consultation->started_at->diffInSeconds(now())
        : 0;

    $consultation->update([
        'ended_at' => now(),
        'status' => 'completed',
    ]);

    $this->logVideoAudit($consultation, 'consultation_ended', [
        'duration_seconds' => $duration,
        'ended_at' => now()->toDateTimeString(),
    ]);

    if (! in_array($appointment->status, ['completed', 'cancelled'], true)) {
        $appointment->update(['status' => 'completed']);
    }

    // Settle wallet escrow (patient already held; pay doctor).
    try {
        app(\Modules\Appointments\Services\AppointmentEscrowService::class)
            ->captureIfHeld($appointment->fresh());
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Escrow capture after video end failed', [
            'appointment_id' => $appointment->id,
            'error' => $e->getMessage(),
        ]);
    }

    return response()->json([
        'success' => true,
        'duration_seconds' => $duration,
        'duration_formatted' => gmdate('H:i:s', $duration)
    ]);
}

    /**
     * تقييم الجلسة — بعد انتهاء الاستشارة فقط، ويحدّث تقييم الطبيب العام.
     */
    public function rate(Request $request, $appointmentId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $appointment = Appointment::query()
            ->where('id', $appointmentId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($appointment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can rate only after the appointment is completed.',
            ], 422);
        }

        $consultation = Consultation::where('appointment_id', $appointmentId)->first();
        if ($consultation) {
            $consultation->update([
                'rating' => $request->rating,
                'patient_feedback' => $request->feedback,
            ]);
        }

        $doctor = $appointment->doctor;
        if ($doctor) {
            $result = app(\Modules\Doctors\Services\DoctorReviewService::class)->rateDoctor([
                'doctor_id' => $doctor->id,
                'rating' => (int) $request->rating,
                'comment' => $request->feedback,
            ], $user);

            if (($result['status'] ?? 200) >= 400) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Could not save rating',
                ], (int) $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'شكراً لتقييمك',
                'average_rating' => $result['average_rating'] ?? null,
                'ratings_count' => $result['ratings_count'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'شكراً لتقييمك',
        ]);
    }

    /**
     * حفظ ملاحظات الطبيب بشكل مشفر.
     */
    public function saveNotes(SaveConsultationNotesRequest $request, $appointmentId)
    {
        $consultation = Consultation::with(['appointment.doctor'])
            ->where('appointment_id', $appointmentId)
            ->firstOrFail();

        $appointment = $consultation->appointment;
        $doctorUserId = $appointment->doctor?->user_id;

        if (auth()->id() !== $doctorUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the doctor can add consultation notes.',
            ], 403);
        }

        $consultation = $this->notesService->saveNotes(
            $consultation,
            $request->input('doctor_notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Consultation notes saved successfully.',
            'data' => [
                'consultation_id' => $consultation->id,
                'doctor_notes' => $this->notesService->getNotes($consultation),
            ],
        ]);
    }

   /**
 * تسجيل أحداث الفيديو مع بيانات محسّنة
 */
private function logVideoAudit(Consultation $consultation, string $event, array $details = []): void
{
    $user = auth()->user();

    if (!$user) {
        return;
    }

    try {
        $auditData = [
            'consultation_id' => $consultation->id,
            'appointment_id' => $consultation->appointment_id,
            'user_id' => $user->id,
            
            // ✅ بيانات المستخدم الموسعة
            'user_role' => $user->getRoleNames()->first() ?? 'user',
            'user_name' => $user->name,
            'user_email' => $user->email,
            
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // ✅ معلومات تقنية إضافية
            'device_type' => $this->getDeviceType(request()->userAgent()),
            'browser' => $this->getBrowser(request()->userAgent()),
            
            'details' => $details,
        ];

        // إضافة وقت الدخول والخروج حسب الحدث
        if ($event === 'participant_joined') {
            $auditData['joined_at'] = now();
        }

        if ($event === 'consultation_ended') {
            $auditData['left_at'] = now();
            $auditData['duration_seconds'] = $details['duration_seconds'] ?? null;
        }

        VideoAudit::create($auditData);
    } catch (\Throwable $e) {
        report($e);
    }
}

/**
 * تحديد نوع الجهاز
 */
private function getDeviceType(?string $userAgent): string
{
    if (!$userAgent) {
        return 'unknown';
    }

    $userAgent = strtolower($userAgent);

    if (str_contains($userAgent, 'mobile')) {
        return 'mobile';
    }

    if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
        return 'tablet';
    }

    return 'desktop';
}

/**
 * تحديد المتصفح
 */
private function getBrowser(?string $userAgent): string
{
    if (!$userAgent) {
        return 'unknown';
    }

    $userAgent = strtolower($userAgent);

    if (str_contains($userAgent, 'chrome') && !str_contains($userAgent, 'edg')) {
        return 'chrome';
    }

    if (str_contains($userAgent, 'firefox')) {
        return 'firefox';
    }

    if (str_contains($userAgent, 'safari') && !str_contains($userAgent, 'chrome')) {
        return 'safari';
    }

    if (str_contains($userAgent, 'edg')) {
        return 'edge';
    }

    if (str_contains($userAgent, 'opera')) {
        return 'opera';
    }

    return 'other';
}
}