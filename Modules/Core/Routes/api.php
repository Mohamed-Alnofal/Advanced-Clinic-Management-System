<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\EmergencyController;
use Modules\Core\Http\Controllers\FcmTokenController;
use Modules\Core\Http\Controllers\HealthController;
use Modules\Core\Http\Controllers\SpecializationController;
use Modules\Core\Services\FirebaseService;

Route::get('/health', HealthController::class);
Route::get('/up', HealthController::class);

Route::prefix('v1/emergency')->middleware('optional.api.auth')->group(function () {
    Route::get('/ai-health', [EmergencyController::class, 'aiHealth']);
    Route::post('/triage', [EmergencyController::class, 'triage']);
});

Route::middleware('auth:api')->prefix('v1/emergency')->group(function () {
    Route::post('/break-glass/{sosSignalId}', [EmergencyController::class, 'viewBreakGlass']);
});

Route::get('/test-fcm', function () {
    $firebase = new FirebaseService();

    $response = $firebase->send(
        "FCM_TOKEN_HERE",
        "Laravel Test",
        "Notification works!"
    );

    return response()->json([
        'status' => true,
        'firebase_response' => $response
    ]);
});

Route::middleware('auth:api')->group(function () {
    Route::post('/fcm/store', [FcmTokenController::class, 'store']);
    Route::post('/fcm/test', [FcmTokenController::class, 'test']);
});

Route::prefix('admin')->middleware(['auth:api','role:admin'])->group(function () {

    Route::get('specializations', [SpecializationController::class, 'index']);
    Route::post('specializations', [SpecializationController::class, 'store']);
    Route::get('specializations/{id}', [SpecializationController::class, 'show']);
    Route::post('specializations/{id}', [SpecializationController::class, 'update']);
    // Route::patch('specializations/{id}', [SpecializationController::class, 'update']);
    Route::delete('specializations/{id}', [SpecializationController::class, 'destroy']);

});