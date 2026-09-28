<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\ProfileController;
use Modules\Users\Http\Controllers\PatientPharmacyController;

// ✅ Routes للمستخدمين العاديين (مسجلين الدخول فقط، بدون دور محدد)
Route::middleware('auth:api')->group(function () {

    Route::get('/profile', [ProfileController::class, 'myProfile']);
    Route::post('/update-profile', [ProfileController::class, 'updateProfile']);
    
    // ✅ هذه الـ Routes للمستخدم العادي (Patient)
    Route::get('/pharmacies/nearby/near', [PatientPharmacyController::class, 'nearby']);
    Route::get('/drugs/search/dugs/user', [PatientPharmacyController::class, 'searchDrug']);
    Route::get('/drugs/{id}/availability', [PatientPharmacyController::class, 'drugAvailability']);
    Route::get('/patient/drugs/suggestions', [PatientPharmacyController::class, 'suggestDrugs']);

    Route::get(
    '/patient/pharmacies/governorate',
    [PatientPharmacyController::class, 'pharmaciesByGovernorate']
);
});

// Numeric ids only so /doctors/holidays and /doctors/monthly-schedule are not swallowed.
Route::get('/doctors/{id}', [ProfileController::class, 'showDoctor'])->whereNumber('id');
Route::get('/pharmacists/{id}', [ProfileController::class, 'showPharmacist'])->whereNumber('id');

