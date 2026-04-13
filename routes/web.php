<?php

use App\Http\Controllers\Dashboard\Master\CafeTableController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::prefix('master')->group(function () {
        Route::resource('cafe', CafeTableController::class)->except(['show']);
        // Cafe Tables
        Route::post('{cafeId}/table', [CafeTableController::class, 'storeTable'])->name('master.cafe.table.store');
        Route::delete('{cafeId}/table/{tableId}', [CafeTableController::class, 'destroyTable'])->name('master.cafe.table.destroy');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
