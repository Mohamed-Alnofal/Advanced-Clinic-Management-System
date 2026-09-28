<?php

use Illuminate\Support\Facades\Route;
use Modules\Disputes\Http\Controllers\DisputesController;
use Modules\Disputes\Http\Controllers\AdminDisputeController;

/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {

    // إنشاء شكوى
    Route::post('/disputes', [
        DisputesController::class,
        'store'
    ]);

    // شكاوي المستخدم
    Route::get('/disputes/my', [
        DisputesController::class,
        'myDisputes'
    ]);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:api',
    'role:admin'
])->prefix('admin')->group(function () {

    Route::get('/disputes', [
        AdminDisputeController::class,
        'index'
    ]);

    Route::get('/disputes/{id}', [
        AdminDisputeController::class,
        'show'
    ]);

    Route::post('/disputes/{id}/resolve', [
        AdminDisputeController::class,
        'resolve'
    ]);

    Route::post('/disputes/{id}/escalate', [
        AdminDisputeController::class,
        'escalate'
    ]);

    Route::patch('/disputes/{id}/status', [
    AdminDisputeController::class,
    'updateStatus'
]);
});