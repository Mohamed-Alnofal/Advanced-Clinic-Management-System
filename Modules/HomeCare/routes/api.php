<?php

use Illuminate\Support\Facades\Route;
use Modules\HomeCare\Http\Controllers\HomeCareController;
use Modules\HomeCare\Http\Controllers\AdminHomeCareController;

// ============================================================
// المستخدم العادي
// ============================================================

Route::middleware([
    'auth:api',
])->group(function () {

// عرض جميع الأوقات المتاحة لمقدم الخدمة
Route::get(
    '/home-care/providers/{id}/available-slots',
    [HomeCareController::class, 'getAvailableSlots']
);
Route::get(
    '/home-care/providers/nearby',
    [HomeCareController::class, 'nearbyProviders']
);
    Route::get(
        '/home-care/specialties',
        [HomeCareController::class, 'specialties']
    );

    Route::get(
        '/home-care',
        [HomeCareController::class, 'index']
    );

    /*
    |--------------------------------------------------------------------------
    | تقديم طلب ليصبح مقدم رعاية
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/home-care/profile',
        [HomeCareController::class, 'storeProfile']
    );

    /*
    |--------------------------------------------------------------------------
    | طلب رعاية منزلية
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/home-care/requests',
        [HomeCareController::class, 'createRequest']
    );

    Route::get(
        '/home-care/my-requests',
        [HomeCareController::class, 'myRequests']
    );

    Route::get(
        '/home-care/my-profile',
        [HomeCareController::class, 'myProfile']
    );
});


// ============================================================
// مقدم الخدمة بعد موافقة الأدمن
// ============================================================

Route::middleware([
    'auth:api',
    'role:provider',
])->group(function () {

    Route::patch(
        '/home-care/location',
        [HomeCareController::class, 'updateLocation']
    );

    Route::get(
        '/home-care/my-availability',
        [HomeCareController::class, 'myAvailability']
    );

    Route::post(
        '/home-care/availability',
        [HomeCareController::class, 'createAvailability']
    );

    Route::put(
        '/home-care/availability/{id}',
        [HomeCareController::class, 'updateAvailability']
    );

    Route::delete(
        '/home-care/availability/{id}',
        [HomeCareController::class, 'deleteAvailability']
    );

    Route::get(
        '/home-care/requests/provider',
        [HomeCareController::class, 'providerRequests']
    );

    Route::patch(
        '/home-care/requests/{id}/status',
        [HomeCareController::class, 'updateRequestStatus']
    );

    Route::post('/home-care/update-profile', [HomeCareController::class, 'updateProfile']);
});


// ============================================================
// الأدمن
// ============================================================

Route::middleware([
    'auth:api',
    'role:admin',
])
->prefix('admin/home-care')
->group(function () {

    Route::get(
        '/pending',
        [AdminHomeCareController::class, 'pendingProfiles']
    );

    Route::put(
        '/approve/{id}',
        [AdminHomeCareController::class, 'approveProfile']
    );

    Route::put(
        '/reject/{id}',
        [AdminHomeCareController::class, 'rejectProfile']
    );

    Route::get(
        '/providers',
        [AdminHomeCareController::class, 'allProviders']
    );

    Route::get(
        '/providers/{id}',
        [AdminHomeCareController::class, 'showProvider']
    );

    Route::get(
        '/stats',
        [AdminHomeCareController::class, 'stats']
    );
});