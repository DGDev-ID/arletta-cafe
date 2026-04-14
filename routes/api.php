<?php

use App\Http\Controllers\Api\GetMenuCafeTableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/get-menu-cafe-table', GetMenuCafeTableController::class);
});