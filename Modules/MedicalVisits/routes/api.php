<?php

use Illuminate\Support\Facades\Route;
use Modules\MedicalVisits\Http\Controllers\FamilyHistoryController;
use Modules\MedicalVisits\Http\Controllers\MedicalVisitsController;
use Modules\MedicalVisits\Http\Controllers\OcularHistoryController;
use Modules\MedicalVisits\Http\Controllers\OphthalmologyRecordController;
use Modules\MedicalVisits\Http\Controllers\SurgeryController;
use Modules\MedicalVisits\Http\Controllers\VitalSignController;

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

Route::middleware(['auth:api'])->prefix('medical-visits')->group(function () {

    Route::get('/', [MedicalVisitsController::class, 'index']);
    Route::post('/', [MedicalVisitsController::class, 'store']);
    Route::get('{id}', [MedicalVisitsController::class, 'show']);
    Route::post('{id}', [MedicalVisitsController::class, 'update']);
    Route::patch('{id}/visibility', [MedicalVisitsController::class, 'updateVisibility']);
    Route::delete('{id}', [MedicalVisitsController::class, 'destroy']);

});

Route::middleware(['auth:api'])->prefix('family-histories')->group(function () {

    Route::get('/', [FamilyHistoryController::class, 'index']);
    Route::post('/', [FamilyHistoryController::class, 'store']);
    Route::get('{id}', [FamilyHistoryController::class, 'show']);
    Route::post('{id}', [FamilyHistoryController::class, 'update']);
    Route::delete('{id}', [FamilyHistoryController::class, 'destroy']);
});

Route::middleware(['auth:api'])->prefix('vital-signs')->group(function () {

    Route::get('/', [VitalSignController::class, 'index']);
    Route::post('/', [VitalSignController::class, 'store']);
    Route::get('{id}', [VitalSignController::class, 'show']);
    Route::post('{id}', [VitalSignController::class, 'update']);
    Route::delete('{id}', [VitalSignController::class, 'destroy']);
});

Route::middleware(['auth:api'])->prefix('ocular-histories')->group(function () {

    Route::get('/', [OcularHistoryController::class, 'index']);
    Route::post('/', [OcularHistoryController::class, 'store']);
    Route::get('{id}', [OcularHistoryController::class, 'show']);
    Route::put('{id}', [OcularHistoryController::class, 'update']);
    Route::delete('{id}', [OcularHistoryController::class, 'destroy']);
});

Route::middleware(['auth:api'])->prefix('surgeries')->group(function () {

    Route::get('/', [SurgeryController::class, 'index']);
    Route::post('/', [SurgeryController::class, 'store']);
    Route::get('{id}', [SurgeryController::class, 'show']);
    Route::post('{id}', [SurgeryController::class, 'update']);
    Route::delete('{id}', [SurgeryController::class, 'destroy']);
});

Route::middleware(['auth:api'])->prefix('ophthalmology-records')->group(function () {

    Route::get('/', [OphthalmologyRecordController::class, 'index']);
    Route::get('/{id}', [OphthalmologyRecordController::class, 'show']);
    Route::post('/', [OphthalmologyRecordController::class, 'store']);
    Route::post('/{id}', [OphthalmologyRecordController::class, 'update']);
    Route::delete('/{id}', [OphthalmologyRecordController::class, 'destroy']);
});