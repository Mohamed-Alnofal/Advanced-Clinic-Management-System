<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinics\Http\Controllers\ClinicController;

Route::middleware('auth:api')->group(function () {
    Route::post('/clinics', [ClinicController::class, 'store']);
    Route::post('/clinics/{clinicId}', [ClinicController::class, 'update']);
    Route::get('/my-clinics', [ClinicController::class, 'myClinics']);
    Route::post('/destroy/{id}', [ClinicController::class, 'destroy']);
});


Route::middleware(['auth:api', 'role:admin'])
    ->prefix('admin')
    ->group(function () {

        // All pending requests
        Route::get(
            'medical-facilities/pending',
            [ClinicController::class, 'pending']
        );

        // Approve request
        Route::post(
            'medical-facilities/{facility}/approve',
            [ClinicController::class, 'approve']
        );

        // Reject request
        Route::post(
            'medical-facilities/{facility}/reject',
            [ClinicController::class, 'reject']
        );
    });