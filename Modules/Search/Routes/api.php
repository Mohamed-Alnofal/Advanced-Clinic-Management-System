<?php

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\SearchController;

Route::middleware('auth:api')->group(function () {

    Route::post('/search-by-name', [SearchController::class, 'searchByName']);

    Route::post('/search-by-governorate', [SearchController::class, 'searchByGovernorate']);

    Route::post('/search-by-location', [SearchController::class, 'searchByLocation']);
});

Route::get('/specializations', [SearchController::class, 'specializations']);

Route::get('/governorates', [SearchController::class, 'governorates']);