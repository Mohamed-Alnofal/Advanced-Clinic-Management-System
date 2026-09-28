<?php

use Illuminate\Support\Facades\Route;
use Modules\Drug\Http\Controllers\DrugController;
use Modules\Drug\Http\Controllers\CategoryController;

Route::middleware(['auth:api'])->group(function () {

    // ========== إدارة التصنيفات (للصيدلي) ==========
    Route::prefix('pharmacist/categories')->middleware(['role:pharmacist'])->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });
    
    // ========== إدارة الأدوية (للصيدلي فقط) ==========
    Route::prefix('drugs')->middleware(['role:pharmacist'])->group(function () {

        Route::get('/', [DrugController::class, 'index']);
        Route::post('/', [DrugController::class, 'store']);

        // Static paths BEFORE /{id} so "pharmacy" / "search" are not captured as ids.
        Route::get('/pharmacy/{pharmacyId}', [DrugController::class, 'getByPharmacy']);
        Route::get('/search/{keyword}', [DrugController::class, 'search']);
        Route::get('/categories', [DrugController::class, 'categories']);
        Route::get('/categories/{id}/drugs', [DrugController::class, 'categoryDrugs']);
        Route::get('/types', [DrugController::class, 'types']);
        Route::get('/types/{type}/drugs', [DrugController::class, 'typeDrugs']);

        Route::get('/{id}', [DrugController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [DrugController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [DrugController::class, 'destroy'])->whereNumber('id');
    });

    
    // // ========== عرض صيدليات الصيدلي (ليختار pharmacy_id) ==========
    // Route::prefix('pharmacist')->middleware(['role:pharmacist'])->group(function () {
    //     Route::get('/my-pharmacies', [DrugController::class, 'myPharmacies']);
    // });
    
    // // ========== عرض الصيدليات (طريقة بديلة) ==========
    // Route::prefix('pharmacies')->middleware(['role:pharmacist'])->group(function () {
    //     Route::get('/', [PharmacyController::class, 'index']);
    //     Route::get('/{id}', [PharmacyController::class, 'show']);
    // });

});
// Route::middleware('auth:api')->group(function () {
//     Route::get(
//         '/drugs/autocomplete',
//         [DrugController::class, 'autocomplete']
//     );
// });
    Route::get('/drugs/autocomplete',[DrugController::class, 'autocomplete']);
