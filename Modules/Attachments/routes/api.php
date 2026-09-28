<?php

use Illuminate\Support\Facades\Route;
use Modules\Attachments\Http\Controllers\AttachmentsController;

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

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('attachments')->group(function () {

        Route::post('/', [
            AttachmentsController::class,
            'store'
        ]);

        Route::get('{attachment}/download', [
            AttachmentsController::class,
            'download'
        ]);

        Route::delete('{attachment}', [
            AttachmentsController::class,
            'destroy'
        ]);

    });

});