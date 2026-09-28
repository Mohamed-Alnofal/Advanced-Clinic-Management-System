<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationsController;

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

Route::middleware('auth:api')->group(function () {

    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::get('/notifications/unread', [NotificationsController::class, 'unread']);

    Route::get('/notifications/count', [NotificationsController::class, 'count']);

    
    Route::post('/notifications/read-all', [NotificationsController::class, 'readAll']);
    
    Route::get('/notifications/{id}', [NotificationsController::class, 'show']);
    
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'read']);
    
    Route::delete('/notifications/{id}', [NotificationsController::class, 'delete']);
});