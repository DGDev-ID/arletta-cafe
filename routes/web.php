<?php

use App\Http\Controllers\Dashboard\Master\CafeTableController;
use App\Http\Controllers\Dashboard\Master\MaterialController;
use App\Http\Controllers\Dashboard\Master\UnitController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('cafe', CafeTableController::class)->except(['show']);
        Route::post('{cafeId}/table', [CafeTableController::class, 'storeTable'])->name('cafe.table.store');
        Route::delete('{cafeId}/table/{tableId}', [CafeTableController::class, 'destroyTable'])->name('cafe.table.destroy');

        Route::resource('unit', UnitController::class)->except(['show']);

        Route::resource('material', MaterialController::class);
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
