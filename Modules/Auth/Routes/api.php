<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
// Route::get('/doctors/{id}', [AuthController::class, 'show']);

Route::post('/admin/doctors/{id}/approve', [AuthController::class, 'approveDoctor']);

Route::post('/login', [AuthController::class, 'login']);

Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:api'])->group(function () {
    
    Route::post('/doctor/reupload-documents', [AuthController::class, 'uploadDocuments']);
    Route::post('/pharmacist/reupload-documents', [AuthController::class, 'uploadPharmacyDocuments']);

});