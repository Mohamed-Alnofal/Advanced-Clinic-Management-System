<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscription\Http\Controllers\SubscriptionController;
use Modules\Subscription\Http\Controllers\AdminSubscriptionController;

Route::middleware('auth:api')
    ->prefix('subscription')
    ->group(function () {
        Route::get('/plans', [SubscriptionController::class, 'plans']);
        Route::post('/wallet', [SubscriptionController::class, 'subscribeWithWallet']);
        Route::post('/manual', [SubscriptionController::class, 'subscribeManually']);
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::patch('/auto-renew', [SubscriptionController::class, 'updateAutoRenew']);
    });

Route::middleware(['auth:api', 'role:admin'])
    ->prefix('admin/subscriptions')
    ->group(function () {
        Route::get('/plans', [AdminSubscriptionController::class, 'plans']);
        Route::post('/plans', [AdminSubscriptionController::class, 'storePlan']);
        Route::put('/plans/{subscriptionPlan}', [AdminSubscriptionController::class, 'updatePlan']);
        Route::delete('/plans/{subscriptionPlan}', [AdminSubscriptionController::class, 'deletePlan']);
        Route::patch('/plans/{subscriptionPlan}/status', [AdminSubscriptionController::class, 'togglePlanStatus']);
    });
