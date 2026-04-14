<?php

use App\Http\Controllers\Api\GetMenuCafeTableController;
use App\Http\Controllers\Dashboard\PublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/get-menu-cafe-table', GetMenuCafeTableController::class);
    Route::get('/queue/public/{cafeId}', [PublicController::class, 'apiPublicQueue']);
    Route::get('/queue/kitchen/{cafeId}', [PublicController::class, 'apiKitchenQueue']);
});