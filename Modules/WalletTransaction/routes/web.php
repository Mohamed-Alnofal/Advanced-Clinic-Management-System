<?php

use Illuminate\Support\Facades\Route;
use Modules\WalletTransaction\Http\Controllers\WalletTransactionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([], function () {
    Route::resource('wallettransaction', WalletTransactionController::class)->names('wallettransaction');
});
