<?php

use Illuminate\Support\Facades\Route;
use Modules\Laboratory\Http\Controllers\DoctorLabRequestController;
use Modules\Laboratory\Http\Controllers\LaboratoryController;
use Modules\Laboratory\Http\Controllers\PatientLaboratoryController;

/*
|--------------------------------------------------------------------------
| Doctor Laboratory Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api', 'role:doctor'])
    ->prefix('doctor/laboratories')
    ->group(function () {
        Route::get('/available', [DoctorLabRequestController::class, 'available']);
        Route::post('/orders', [DoctorLabRequestController::class, 'store']);
        Route::get('/orders', [DoctorLabRequestController::class, 'index']);
        Route::get('/orders/{laboratoryOrder}', [DoctorLabRequestController::class, 'show']);
        Route::post('/orders/{orderId}', [DoctorLabRequestController::class, 'update']);
        Route::delete('/orders/{orderId}', [DoctorLabRequestController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| Patient laboratory orders (clinic-ordered results)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])
    ->prefix('patient/laboratory')
    ->group(function () {
        Route::get('/orders', [PatientLaboratoryController::class, 'orders']);
        Route::get('/orders/{laboratoryOrder}', [PatientLaboratoryController::class, 'show']);
    });

/*
|--------------------------------------------------------------------------
| Laboratory staff routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api', 'role:laboratory'])
    ->prefix('laboratory')
    ->group(function () {
        Route::get('/orders', [LaboratoryController::class, 'orders']);
        Route::get('/orders/{laboratoryOrder}', [LaboratoryController::class, 'showOrder']);
        Route::post('/orders/{laboratoryOrder}/start', [LaboratoryController::class, 'startOrder']);
        Route::post('/orders/{laboratoryOrder}/result', [LaboratoryController::class, 'uploadResult']);
        Route::post('/analyze-assist', [LaboratoryController::class, 'analyzeAssist']);
    });

/*
|--------------------------------------------------------------------------
| Shared authenticated laboratory result routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {
    Route::get(
        '/laboratory-results/{laboratoryResult}/download',
        [LaboratoryController::class, 'download']
    );

    Route::get(
        '/laboratory/laboratory-orders/{laboratoryOrder}/results',
        [LaboratoryController::class, 'getResults']
    );
});
