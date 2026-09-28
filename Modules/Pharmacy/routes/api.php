<?php

use Illuminate\Support\Facades\Route;
use Modules\Pharmacy\Http\Controllers\PharmacyController;

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

Route::prefix('pharmacies')->middleware('auth:api')->group(function () {

   // 🏥 إنشاء صيدلية
        Route::post('/', [PharmacyController::class, 'store']);

        // 📋 عرض كل صيدليات الصيدلي
        Route::get('/', [PharmacyController::class, 'index']);

        // 👁 عرض صيدلية واحدة
        Route::get('/{id}', [PharmacyController::class, 'show']);

        // ✏️ تعديل صيدلية
        Route::put('/{id}', [PharmacyController::class, 'update']);

        // ❌ حذف صيدلية
        Route::delete('/{id}', [PharmacyController::class, 'destroy']);
});

