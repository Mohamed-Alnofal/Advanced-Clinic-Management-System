<?php

use Illuminate\Support\Facades\Route;
use Modules\Wallet\Http\Controllers\WalletController;

Route::middleware(['auth:api'])->prefix('wallet')->group(function () {
    Route::post('/create', [WalletController::class, 'create']);
    Route::get('/', [WalletController::class, 'balance']);
    Route::post('/top-up', [WalletController::class, 'topUp']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/getBalance', [WalletController::class, 'getBalance']);
    Route::get('/getTransactions', [WalletController::class, 'getTransactions']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
});

Route::get('/wallet/stripe-success', [WalletController::class, 'stripeSuccess']);
Route::post('/stripe/webhook', [WalletController::class, 'handle']);
