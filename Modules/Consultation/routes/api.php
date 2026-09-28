<?php

use Illuminate\Support\Facades\Route;
use Modules\Consultation\Http\Controllers\ConsultationController;
use Modules\Consultation\Http\Controllers\LiveKitWebhookController;
use Modules\Consultation\Http\Controllers\VideoSubscriptionController;
use Modules\Consultation\Http\Controllers\AdminConsultationController;

// ============================================================
// 🔓 Webhook (عام - بدون مصادقة)
// ============================================================
Route::post('/livekit/webhook', [LiveKitWebhookController::class, 'handle']);

// ============================================================
// 🔐 Routes القديمة (تبقى كما هي - جميع المستخدمين المصادقين)
// ============================================================
Route::middleware('jwt.auth')->prefix('consultations')->group(function () {
    
    // توليد توكين - محدود 5 مرات في الدقيقة
    Route::get('/token/{appointmentId}', [ConsultationController::class, 'getToken'])
        ->middleware('throttle:consultation-token');
    
    // بدء المكالمة - محدود 10 مرات في الساعة
    Route::post('/start/{appointmentId}', [ConsultationController::class, 'start'])
        ->middleware('throttle:consultation-start');
    
    // الانضمام للمكالمة - محدود 3 مرات في الدقيقة
    Route::post('/join/{appointmentId}', [ConsultationController::class, 'join'])
        ->middleware('throttle:consultation-join');
    
    // إنهاء المكالمة
    Route::post('/end/{appointmentId}', [ConsultationController::class, 'end']);
    
    // تقييم - 1 مرة في الساعة لكل مكالمة
    Route::post('/rate/{appointmentId}', [ConsultationController::class, 'rate'])
        ->middleware('throttle:1,60');

    // ملاحظات الطبيب
    Route::post('/{appointmentId}/notes', [ConsultationController::class, 'saveNotes'])
        ->middleware('throttle:consultation-start');
    
    Route::get('/{appointmentId}', [ConsultationController::class, 'show']);
});

// ============================================================
// 📺 Routes القديمة للاشتراكات (تبقى كما هي)
// ============================================================
Route::middleware('jwt.auth')->group(function () {

    Route::post(
        '/video-subscription/{doctorId}',
        [VideoSubscriptionController::class, 'toggle']
    );

    Route::get(
        '/video-subscription/{doctorId}',
        [VideoSubscriptionController::class, 'status']
    );
});

// ============================================================
// 🆕 Routes جديدة للطبيب فقط (لا تؤثر على القديمة)
// ============================================================
Route::middleware(['jwt.auth', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::get('/consultations', [ConsultationController::class, 'doctorConsultations']);
    Route::get('/consultations/{id}', [ConsultationController::class, 'doctorConsultation']);
});

// ============================================================
// 🆕 Routes جديدة للمريض فقط (لا تؤثر على القديمة)
// ============================================================
Route::middleware(['jwt.auth', 'role:user'])->prefix('patient')->group(function () {
    Route::get('/consultations', [ConsultationController::class, 'patientConsultations']);
    Route::get('/consultations/{id}', [ConsultationController::class, 'patientConsultation']);
});

// ============================================================
// 🆕 Routes جديدة للمشترك (لا تؤثر على القديمة)
// ============================================================
Route::middleware(['jwt.auth', 'role:user'])->prefix('subscriber')->group(function () {
    Route::get('/doctors', [VideoSubscriptionController::class, 'subscribedDoctors']);
    Route::post('/toggle/{doctorId}', [VideoSubscriptionController::class, 'toggle']);
    Route::get('/status/{doctorId}', [VideoSubscriptionController::class, 'status']);
});

// ============================================================
// 🛡️ Routes جديدة للمدير الإداري فقط (جديدة كلياً)
// ============================================================
Route::middleware(['jwt.auth', 'role:admin'])->prefix('admin')->group(function () {

    // ========== إدارة الاستشارات ==========
    Route::get('/consultations', [AdminConsultationController::class, 'index']);
    Route::get('/consultations/{id}', [AdminConsultationController::class, 'show']);
    Route::put('/consultations/{id}/cancel', [AdminConsultationController::class, 'cancel']);
    Route::delete('/consultations/{id}', [AdminConsultationController::class, 'destroy']);

    // ========== سجلات المكالمات ==========
    Route::get('/consultations/audits', [AdminConsultationController::class, 'audits']);
    Route::get('/consultations/{id}/audits', [AdminConsultationController::class, 'consultationAudits']);
    Route::get('/consultations/audits/export', [AdminConsultationController::class, 'exportAudits']);

    // ========== إحصائيات وتقارير ==========
    Route::get('/consultations/stats', [AdminConsultationController::class, 'stats']);
    Route::get('/consultations/reports', [AdminConsultationController::class, 'reports']);

    // ========== إدارة المشتركين ==========
    Route::get('/subscribers', [AdminConsultationController::class, 'subscribers']);
    Route::get('/subscribers/{doctorId}', [AdminConsultationController::class, 'doctorSubscribers']);
});