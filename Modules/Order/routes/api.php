<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;
use Modules\Order\Http\Controllers\PharmacyOrderStreamController;

// use Modules\Order\Http\Controllers\OrderController;



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

Route::get('/pharmacist/orders/stream', [PharmacyOrderStreamController::class, 'stream']);

Route::middleware('auth:api')->group(function () {

    // Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/orders/{id}', [OrderController::class, 'show']);

    Route::get('/orders', [OrderController::class, 'index']);

    Route::post('/orders/{id}', [OrderController::class, 'update']);

    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);


    Route::get('/pharmacist/orders', [OrderController::class, 'pharmacistOrders']);

    // Start Processing (Lock)
    Route::post('/orders/{id}/start-processing',[OrderController::class, 'startProcessing']);
    // Accept Order
    Route::post('/orders/{id}/accept', [OrderController::class, 'accept']);
    // Reject Order
    Route::post('/orders/{id}/reject', [OrderController::class, 'reject']);

});