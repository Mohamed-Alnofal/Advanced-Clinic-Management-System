<?php

use Illuminate\Support\Facades\Route;
use Modules\Pharmacist\Http\Controllers\PharmacistController;

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
    Route::apiResource('pharmacist', PharmacistController::class)->names('pharmacist');
});
