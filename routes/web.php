<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\Master\CafeTableController;
use App\Http\Controllers\Dashboard\Master\MaterialController;
use App\Http\Controllers\Dashboard\Master\MenuCategoryController;
use App\Http\Controllers\Dashboard\Master\GalleryController;
use App\Http\Controllers\Dashboard\Master\MenuController;
use App\Http\Controllers\Dashboard\Master\UnitController;
use App\Http\Controllers\Dashboard\UserManagement\ManageAdminController;
use App\Http\Controllers\Dashboard\UserManagement\ManageCashierController;
use App\Http\Controllers\Dashboard\UserManagement\ManageBackofficeController;
use App\Http\Controllers\Dashboard\Management\UnitMaterialConverterController;
use App\Http\Controllers\Dashboard\Management\InboundOutboundMaterialController;
use App\Http\Controllers\Dashboard\PublicController;
use App\Http\Controllers\Dashboard\Transaction\HistoryTransactionController;
use App\Http\Controllers\Dashboard\Transaction\CashierController;
use App\Http\Controllers\Dashboard\Shortcut\PublicLinkGeneratorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('cafe', CafeTableController::class)->except(['show']);
        Route::post('{cafeId}/table', [CafeTableController::class, 'storeTable'])->name('cafe.table.store');
        Route::delete('{cafeId}/table/{tableId}', [CafeTableController::class, 'destroyTable'])->name('cafe.table.destroy');

        Route::resource('unit', UnitController::class)->except(['show']);

        Route::resource('material', MaterialController::class);

        Route::resource('menu-category', MenuCategoryController::class)->except(['show']);

        Route::resource('menu', MenuController::class);
        Route::patch('menu/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])->name('menu.toggle-status');

        Route::resource('gallery', GalleryController::class)->except(['show']);
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [ManageAdminController::class, 'index'])->name('index');
            Route::get('/search-users', [ManageAdminController::class, 'searchUsers'])->name('search-users');
            Route::post('/assign', [ManageAdminController::class, 'assign'])->name('assign');
            Route::delete('/{id}/revoke', [ManageAdminController::class, 'revoke'])->name('revoke');
        });

        Route::prefix('cashier')->name('cashier.')->group(function () {
            Route::get('/', [ManageCashierController::class, 'index'])->name('index');
            Route::get('/search-users', [ManageCashierController::class, 'searchUsers'])->name('search-users');
            Route::post('/assign', [ManageCashierController::class, 'assign'])->name('assign');
            Route::delete('/{id}/revoke', [ManageCashierController::class, 'revoke'])->name('revoke');
        });

        Route::prefix('backoffice')->name('backoffice.')->group(function () {
            Route::get('/', [ManageBackofficeController::class, 'index'])->name('index');
            Route::get('/search-users', [ManageBackofficeController::class, 'searchUsers'])->name('search-users');
            Route::post('/assign', [ManageBackofficeController::class, 'assign'])->name('assign');
            Route::delete('/{id}/revoke', [ManageBackofficeController::class, 'revoke'])->name('revoke');
        });
    });

    Route::prefix('management')->name('management.')->group(function () {
        Route::resource('unit-material-converter', UnitMaterialConverterController::class)->only(['index', 'show']);
        Route::post('unit-material-converter/{materialId}/converter', [UnitMaterialConverterController::class, 'store'])->name('unit-material-converter.store');
        Route::put('unit-material-converter/{materialId}/converter/{converterId}', [UnitMaterialConverterController::class, 'update'])->name('unit-material-converter.update');
        Route::delete('unit-material-converter/{materialId}/converter/{converterId}', [UnitMaterialConverterController::class, 'destroy'])->name('unit-material-converter.destroy');

        Route::get('inbound-outbound-material', [InboundOutboundMaterialController::class, 'index'])->name('inbound-outbound-material.index');
        Route::get('inbound-outbound-material/create', [InboundOutboundMaterialController::class, 'create'])->name('inbound-outbound-material.create');
        Route::post('inbound-outbound-material', [InboundOutboundMaterialController::class, 'store'])->name('inbound-outbound-material.store');
        Route::get('inbound-outbound-material/materials-by-cafe', [InboundOutboundMaterialController::class, 'getMaterialsByCafe'])->name('inbound-outbound-material.materials-by-cafe');
        Route::get('inbound-outbound-material/check-unit-converter', [InboundOutboundMaterialController::class, 'checkUnitConverter'])->name('inbound-outbound-material.check-unit-converter');
    });

    Route::prefix('transaction')->name('transaction.')->group(function () {
        Route::get('history', [HistoryTransactionController::class, 'index'])->name('history.index');
        Route::get('history/export', [HistoryTransactionController::class, 'export'])->name('history.export');
        Route::get('history/{id}', [HistoryTransactionController::class, 'show'])->name('history.show');

        Route::get('cashier', [CashierController::class, 'index'])->name('cashier.index');
        Route::get('cashier/{id}', [CashierController::class, 'show'])->name('cashier.show');
        Route::patch('cashier/{id}/success', [CashierController::class, 'makeSuccess'])->name('cashier.success');
        Route::patch('cashier/{id}/failed', [CashierController::class, 'makeFailed'])->name('cashier.failed');
        Route::patch('cashier/{id}/success-in-order', [CashierController::class, 'makeSuccessInOrder'])->name('cashier.success-in-order');
        Route::get('cashier/{id}/receipt', [CashierController::class, 'printReceipt'])->name('cashier.receipt');
        Route::get('cashier/{id}/receipt-data', [CashierController::class, 'receiptData'])->name('cashier.receipt-data');
        Route::get('cashier/search-qr/{qr_code}', [CashierController::class, 'searchByQRCode'])->name('search-qr');
    });

    Route::prefix('shortcut')->name('shortcut.')->group(function () {
        Route::get('public-link-generator', [PublicLinkGeneratorController::class, 'index'])->name('public-link-generator.index');
    });
});

Route::get('for-public/{cafeId}', [PublicController::class, 'forPublic']);
Route::get('for-kitchen/{cafeId}', [PublicController::class, 'forKitchen']);

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
