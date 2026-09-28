<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;
use Modules\Payment\Http\Controllers\PaymentStripeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Stripe return pages (opened in mobile browser after Checkout).
Route::get('/payment/stripe/success', [PaymentStripeController::class, 'success']);
Route::get('/payment/stripe/cancel', [PaymentStripeController::class, 'cancel']);

Route::middleware(['auth:api'])
    ->prefix('v1/payment')
    ->group(function () {
        Route::post('/pay', [PaymentController::class, 'pay']);
    });
