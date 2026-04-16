<?php

use App\Http\Controllers\Api\CheckMenuAvailableController;
use App\Http\Controllers\Api\CheckMenusAvailableController;
use App\Http\Controllers\Api\GetMenuCafeTableController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Dashboard\PublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/landing-page', LandingPageController::class);
    Route::get('/get-menu-cafe-table', GetMenuCafeTableController::class);

    Route::get('/queue/public/{cafeId}', [PublicController::class, 'apiPublicQueue']);
    Route::get('/queue/kitchen/{cafeId}', [PublicController::class, 'apiKitchenQueue']);

    Route::post('/check-available-materials', CheckMenuAvailableController::class);
    Route::post('/check-available-materials/bulk', CheckMenusAvailableController::class);
});