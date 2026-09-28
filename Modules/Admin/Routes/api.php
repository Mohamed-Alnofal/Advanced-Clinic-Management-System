<?php

use Illuminate\Support\Facades\Route;

use Modules\Admin\Http\Controllers\UserController;
use Modules\Admin\Http\Controllers\AuditLogController;
use Modules\Admin\Http\Controllers\ConsultationHistoryController;
use Modules\Admin\Http\Controllers\AnalyticsController;
use Modules\Admin\Http\Controllers\AdminVerificationController;
use Modules\Admin\Http\Controllers\ReportController;
use Modules\Admin\Http\Controllers\AdminPharmacyController;
use Modules\Auth\Http\Controllers\AuthController;

Route::middleware(['auth:api', 'role:admin', 'audit'])
    ->prefix('admin')
    ->group(function () {

        // Users
        Route::prefix('users')->group(function () {

            Route::get('/', [UserController::class, 'index']);
            Route::patch('{id}/activate', [UserController::class, 'activate']);
            Route::patch('{id}/suspend', [UserController::class, 'suspend']);
            Route::delete('{id}', [UserController::class, 'destroy']);
        });

        // Audit
        Route::prefix('audit')->group(function () {

            Route::get('logs', [AuditLogController::class, 'index']);
            Route::get('statistics', [AuditLogController::class, 'statistics']);
        });

        // Analytics
        Route::prefix('analytics')->group(function () {

            Route::get('/', [AnalyticsController::class, 'index']);
            Route::get('/users', [AnalyticsController::class, 'userStats']);
            Route::get('/doctors', [AnalyticsController::class, 'doctorStats']);
            Route::get('/appointments', [AnalyticsController::class, 'appointmentStats']);
            Route::get('/activity', [AnalyticsController::class, 'activityStats']);
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        });

        // Verification
        Route::prefix('verification')->group(function () {

            Route::get('/doctors/unverified', [AdminVerificationController::class, 'unverifiedDoctors']);
            Route::post('/doctors/{id}/approve', [AdminVerificationController::class, 'approveDoctor']);
            Route::post('/doctors/{id}/reject', [AdminVerificationController::class, 'rejectDoctor']);

            Route::get('/pharmacists/unverified', [AdminVerificationController::class, 'unverifiedPharmacists']);
            Route::post('/pharmacists/{id}/approve', [AdminVerificationController::class, 'approvePharmacist']);
            Route::post('/pharmacists/{id}/reject', [AdminVerificationController::class, 'rejectPharmacist']);

            Route::get('/pending-stats', [AdminVerificationController::class, 'pendingStats']);



            //للصيدلية

// 📌 قبول الصيدلية
            Route::post('/pharmacies/{id}/approve', [
                AdminPharmacyController::class,
                'approvePharmacy'
            ]);

            // 📌 رفض الصيدلية
            Route::post('/pharmacies/{id}/reject', [
                AdminPharmacyController::class,
                'rejectPharmacy'
            ]);

            // 📌 عرض الصيدليات غير الموثقة
            Route::get('/pharmacies/unverified', [
                AdminPharmacyController::class,
                'unverifiedPharmacies'
            ]);
        });


                    // ===============================
            // Ambulance Users
            // ===============================

            // عرض حسابات الإسعاف بانتظار الموافقة
            Route::get('/ambulance-users/unverified', [
                AdminVerificationController::class,
                'pendingAmbulanceUsers'
            ]);

            // قبول أو رفض حساب الإسعاف
            Route::post('/ambulance-users/{id}', [
                AdminVerificationController::class,
                'ambulanceUserAction'
            ]);

            // ===============================
            // Ambulance Companies
            // ===============================

            // عرض شركات الإسعاف بانتظار الموافقة
            Route::get('/ambulance-companies/pending', [
                AdminVerificationController::class,
                'pendingAmbulanceCompanies'
            ]);

            // قبول أو رفض شركة الإسعاف
            Route::post('/ambulance-companies/{id}', [
                AdminVerificationController::class,
                'ambulanceCompanyAction'
            ]);

        // Reports
        Route::prefix('reports')->group(function () {

            Route::get('/new-doctors', [ReportController::class, 'newDoctors']);
            Route::get('/cancelled-appointments', [ReportController::class, 'cancelledAppointments']);
        });

        // Financials
        Route::prefix('financials')->group(function () {
            Route::get('/summary', [\Modules\Admin\Http\Controllers\FinancialsController::class, 'summary']);
            Route::get('/trend', [\Modules\Admin\Http\Controllers\FinancialsController::class, 'trend']);
            Route::get('/by-provider', [\Modules\Admin\Http\Controllers\FinancialsController::class, 'byProvider']);
            Route::get('/transactions', [\Modules\Admin\Http\Controllers\FinancialsController::class, 'transactions']);
        });

        // Wallets (admin provisioning — required for subscription payouts)
        Route::prefix('wallets')->group(function () {
            Route::get('/dashboard', [\Modules\Admin\Http\Controllers\AdminWalletController::class, 'dashboard']);
            Route::get('/transactions', [\Modules\Admin\Http\Controllers\AdminWalletController::class, 'transactions']);
            Route::get('/', [\Modules\Admin\Http\Controllers\AdminWalletController::class, 'index']);
            Route::post('/', [\Modules\Admin\Http\Controllers\AdminWalletController::class, 'store']);
            Route::post('/ensure-admin', [\Modules\Admin\Http\Controllers\AdminWalletController::class, 'ensureAdminWallet']);
        });

        Route::prefix('consultations')->group(function () {
            Route::get('/history', [ConsultationHistoryController::class, 'index']);
            Route::get('/{appointmentId}/history', [ConsultationHistoryController::class, 'show']);
        });
    });

Route::middleware(['auth:api', 'role:admin', 'audit'])->group(function () {

    Route::post('/approve-account/{id}', [AuthController::class, 'approveAccount']);
});

