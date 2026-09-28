<?php

use Illuminate\Support\Facades\Route;
use Modules\Appointments\Http\Controllers\AppointmentController;

/*
|--------------------------------------------------------------------------
| API Routes - Appointments Module
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {

    // =============================================
    // 📅 الـ Routes القديمة (للمريض + النظام)
    // =============================================
    Route::post('/appointments/book', [AppointmentController::class, 'book']);
    Route::post('/appointments/pay', [AppointmentController::class, 'pay']);
    Route::post('/appointment/reschedule', [AppointmentController::class, 'reschedule']);
    Route::post('/appointments/cancel', [AppointmentController::class, 'cancel']);
    Route::delete('/appointments/delete', [AppointmentController::class, 'delete']);

    // ⚠️ مهم: تبقى فقط هذه للتوافق القديم
    Route::post('/doctor/appointments/action', [AppointmentController::class, 'action']);

    Route::get('/my-appointments', [AppointmentController::class, 'myAppointments']);

    // =============================================
    // 🩺 Routes الطبيب (DO NOT REMOVE)
    // =============================================
    Route::get('/doctor/appointments/pending', [AppointmentController::class, 'doctorPendingRequests']);

    Route::get('/doctor/appointments/upcoming', [AppointmentController::class, 'doctorUpcomingAppointments']);

    Route::get('/doctor/appointments/all', [AppointmentController::class, 'doctorAllAppointments']);

    Route::get('/doctor/appointments/video/pending', [AppointmentController::class, 'doctorVideoPendingRequests']);

    // =============================================
    // 👤 Routes المريض
    // =============================================
    Route::get('/patient/appointments/upcoming', [AppointmentController::class, 'myUpcomingAppointments']);

    Route::get('/patient/appointments/video', [AppointmentController::class, 'myVideoAppointments']);

    Route::get('/patient/appointments/nearby', [AppointmentController::class, 'checkNearbyAppointments']);

    Route::get('/patient/appointments', [AppointmentController::class, 'myAppointments']);
});

// =============================================
// 🔔 FCM Testing (للتجربة)
// =============================================
Route::get('/test-fcm', function () {
    $token = "dsE3VS5XtDYoJTCxtznQvU:APA91bEvIlxQlFw6lx7vV33NN2fF0t0sjmawne_YxN_Op31tCxajmXsIAIZU9FmYrtcR46KSQc1FfvyS3xwrCwS5ai61G82nzjYxcxDoRqLrFBSgIhwzf6AE";

    app('firebase.messaging')->send(
        Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
            ->withNotification(
                Kreait\Firebase\Messaging\Notification::create('Test 🔔', 'Notification works!')
            )
    );

    return "Notification sent";
});