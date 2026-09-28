<?php

use Illuminate\Support\Facades\Route;
use Modules\Stripe\Http\Controllers\StripeController;

/*
|--------------------------------------------------------------------------
| Stripe module API routes
|--------------------------------------------------------------------------
| Webhooks MUST go through Modules/Wallet (WalletTopUpService + ledger).
| Legacy StripeWebhookController is disabled to prevent double-credit races.
*/

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('stripe', StripeController::class)->names('stripe');
});
