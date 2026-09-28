<?php

use Illuminate\Support\Facades\Route;
use Modules\Ambulance\Http\Controllers\AmbulanceCompanyController;
use Modules\Ambulance\Http\Controllers\AmbulanceDriverAuthController;
use Modules\Ambulance\Http\Controllers\AmbulanceDriverController;
use Modules\Ambulance\Http\Controllers\AmbulanceUnitController;
use Modules\Ambulance\Http\Controllers\AmbulanceUnitStreamController;
use Modules\Ambulance\Http\Controllers\DispatchController;
use Modules\Ambulance\Http\Controllers\DriverMissionController;
use Modules\Ambulance\Http\Controllers\EmergencyMissionController;
use Modules\Ambulance\Http\Controllers\SosController;
use Modules\Ambulance\Http\Controllers\TrackingController;

/*
|--------------------------------------------------------------------------
| Driver mobile APIs (Flutter) — auth:ambulance-driver
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->group(function () {
    Route::post('/login', [AmbulanceDriverAuthController::class, 'login']);

    Route::middleware('auth:ambulance-driver')->group(function () {
        Route::post('/logout', [AmbulanceDriverAuthController::class, 'logout']);
        Route::get('/requests', [DriverMissionController::class, 'requests']);
        Route::post('/requests/{id}/accept', [DriverMissionController::class, 'accept']);
        Route::post('/requests/{id}/reject', [DriverMissionController::class, 'reject']);
        Route::post('/requests/{id}/arrived', [DriverMissionController::class, 'arrived']);
        Route::post('/requests/{id}/complete', [DriverMissionController::class, 'complete']);
        Route::get('/mission', [DriverMissionController::class, 'active']);
        Route::post('/mission/{id}/accept', [DriverMissionController::class, 'accept']);
        Route::post('/mission/{id}/arrived', [DriverMissionController::class, 'arrived']);
        Route::post('/mission/{id}/complete', [DriverMissionController::class, 'complete']);
        Route::post('/tracking/location', [DriverMissionController::class, 'updateLocation']);
        Route::get('/unit', [DriverMissionController::class, 'unit']);
        Route::post('/unit/status', [DriverMissionController::class, 'updateStatus']);
    });
});

// SSE cannot always send Authorization headers; controller authenticates via cookie or ?token=
Route::get('/ambulance/units/stream', [AmbulanceUnitStreamController::class, 'stream']);

/*
|--------------------------------------------------------------------------
| Patient + shared ambulance APIs — auth:api (JWT)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('ambulance')->group(function () {
    Route::post('/sos', [SosController::class, 'store']);
    Route::get('/sos/{id}', [SosController::class, 'show']);
    Route::get('/my-request', [EmergencyMissionController::class, 'myRequest']);
    Route::get('/history', [EmergencyMissionController::class, 'history']);
    Route::get('/request/{id}/eta', [EmergencyMissionController::class, 'eta']);
    Route::get('/request/{id}/track', [EmergencyMissionController::class, 'track']);
    Route::post('/request/{id}/cancel', [EmergencyMissionController::class, 'cancel']);

    Route::post('/dispatch/{id}', [DispatchController::class, 'assign'])->middleware('role:ambulance');
    Route::post('/tracking/location', [TrackingController::class, 'update'])->middleware('role:ambulance');
});

/*
|--------------------------------------------------------------------------
| Ambulance company web portal — auth:api + role:ambulance
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'role:ambulance'])
    ->prefix('ambulance')
    ->group(function () {
        Route::get('/company', [AmbulanceCompanyController::class, 'myCompany']);
        Route::post('/company', [AmbulanceCompanyController::class, 'store']);
        // Location before any `{id}` route so "location" is never captured as an id.
        Route::patch('/company/location', [AmbulanceCompanyController::class, 'updateLocation']);
        Route::patch('/company', [AmbulanceCompanyController::class, 'update']);
        Route::get('/company/history', [EmergencyMissionController::class, 'companyHistory']);

        Route::get('/units', [AmbulanceUnitController::class, 'index']);
        Route::post('/units', [AmbulanceUnitController::class, 'store']);
        Route::put('/units/{unit}', [AmbulanceUnitController::class, 'update']);
        Route::patch('/units/{unit}/location', [AmbulanceUnitController::class, 'updateLocation']);
        Route::patch('/units/{unit}/status', [AmbulanceUnitController::class, 'updateStatus']);
        Route::delete('/units/{unit}', [AmbulanceUnitController::class, 'destroy']);
        Route::post('/units/{unit}/generate-pairing', [AmbulanceUnitController::class, 'generatePairing']);

        Route::get('/drivers', [AmbulanceDriverController::class, 'index']);
        Route::post('/drivers', [AmbulanceDriverController::class, 'store']);
        Route::put('/drivers/{driver}', [AmbulanceDriverController::class, 'update']);
        Route::delete('/drivers/{driver}', [AmbulanceDriverController::class, 'destroy']);
    });
