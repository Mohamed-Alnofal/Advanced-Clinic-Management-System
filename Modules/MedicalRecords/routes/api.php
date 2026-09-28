<?php

use Illuminate\Support\Facades\Route;
use Modules\MedicalRecords\Http\Controllers\MedicalRecordsController;

/*
| Lab PDF/TXT analysis (NLP) — patient JWT (auth:api)
| Base: /api/v1/medical-records
*/
Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::prefix('medical-records')->group(function () {
        Route::get('/', [MedicalRecordsController::class, 'index']);
        Route::post('/', [MedicalRecordsController::class, 'store']);
        // Static segments before {id} to avoid route collisions.
        Route::get('{id}/status', [MedicalRecordsController::class, 'status'])->whereNumber('id');
        Route::get('{id}/download', [MedicalRecordsController::class, 'download'])->whereNumber('id');
        Route::get('{id}', [MedicalRecordsController::class, 'show'])->whereNumber('id');
        Route::delete('{id}', [MedicalRecordsController::class, 'destroy'])->whereNumber('id');
    });
});
