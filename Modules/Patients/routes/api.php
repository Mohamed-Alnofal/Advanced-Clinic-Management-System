<?php

use Illuminate\Support\Facades\Route;
use Modules\Patients\Http\Controllers\PatientAccessRequestController;
use Modules\Patients\Http\Controllers\PatientFileController;
use Modules\Patients\Http\Controllers\PatientsController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('patients', PatientsController::class)->names('patients');
});
Route::middleware(['auth:api'])->group(function () {

    Route::prefix('patients')->group(function () {

         Route::post('/', [PatientsController::class, 'store']);

        Route::post('/search', [PatientsController::class, 'patients']);

        Route::get('/me', [PatientsController::class, 'me']);

        Route::get('{patient}/history', [PatientsController::class, 'history']);

        Route::get('{patient}/export', [PatientsController::class, 'export']);

        Route::get('{patient}', [PatientsController::class, 'show']);

    });

    Route::prefix('patient-files')->group(function () {

         Route::post('/', [PatientFileController::class, 'store']);

        Route::get('{file}', [PatientFileController::class, 'show']);

    });

});


Route::middleware('auth:api')->group(function () {

    Route::post(
        '/patient-access/request',
        [PatientAccessRequestController::class, 'requestAccess']
    );

    Route::get(
        '/patient-access/pending',
        [PatientAccessRequestController::class, 'pendingRequests']
    );

    // Backward-compatible alias (patient id is taken from auth, not URL)
    Route::get(
        '/patient-access/pending/{patientId}',
        [PatientAccessRequestController::class, 'pendingRequests']
    );

    Route::post(
        '/patient-access/approve',
        [PatientAccessRequestController::class, 'approve']
    );

    Route::post(
        '/patient-access/reject',
        [PatientAccessRequestController::class, 'reject']
    );
});