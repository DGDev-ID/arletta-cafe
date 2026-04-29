<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\Master\CafeTableController;
use App\Http\Controllers\Dashboard\Master\MaterialController;
use App\Http\Controllers\Dashboard\Master\MenuCategoryController;
use App\Http\Controllers\Dashboard\Master\GalleryController;
use App\Http\Controllers\Dashboard\Master\MenuController;
use App\Http\Controllers\Dashboard\Master\SemiFinishedMaterialController;
use App\Http\Controllers\Dashboard\Master\UnitController;
use App\Http\Controllers\Dashboard\UserManagement\ManageAdminController;
use App\Http\Controllers\Dashboard\UserManagement\ManageCashierController;
use App\Http\Controllers\Dashboard\UserManagement\ManageBackofficeController;
use App\Http\Controllers\Dashboard\Management\UnitMaterialConverterController;
use App\Http\Controllers\Dashboard\Management\InboundOutboundMaterialController;
use App\Http\Controllers\Dashboard\Management\ExpenseController;
use App\Http\Controllers\Dashboard\PublicController;
use App\Http\Controllers\Dashboard\Transaction\HistoryTransactionController;
use App\Http\Controllers\Dashboard\Transaction\CashierController;
use App\Http\Controllers\Dashboard\Shortcut\PublicLinkGeneratorController;
use App\Http\Controllers\Dashboard\UserManagement\ManageUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\UserManagement\RolePermissionController;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// lightweight JSON endpoint for dashboard top menus today (used by frontend polling)
Route::get('dashboard/top-menus-today', [DashboardController::class, 'topMenusToday'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.top-menus-today');

Route::middleware(['auth'])->group(function () {
    Route::prefix('master')->name('master.')->group(function () {
        Route::post('cafe/{cafeId}/table', [CafeTableController::class, 'storeTable'])->name('cafe.table.store');
        Route::delete('cafe/{cafeId}/table/{tableId}', [CafeTableController::class, 'destroyTable'])->name('cafe.table.destroy');
        Route::patch('cafe/{cafeId}/table/{tableId}/toggle-open-bill', [CafeTableController::class, 'toggleOpenBill'])
            ->name('cafe.table.toggle-open-bill')
            ->middleware('can:master.cafe.update');
        Route::resource('cafe', CafeTableController::class)
            ->except(['show'])
            ->middleware([
                'can:master.cafe.view',
                'can:master.cafe.create',
                'can:master.cafe.update',
                'can:master.cafe.delete',
            ]);

        Route::resource('unit', UnitController::class)
            ->except(['show'])
            ->middleware([
                'can:master.unit.view',
                'can:master.unit.create',
                'can:master.unit.update',
                'can:master.unit.delete',
            ]);

        Route::resource('material', MaterialController::class)
            ->middleware([
                'can:master.material.view',
                'can:master.material.create',
                'can:master.material.update',
                'can:master.material.delete',
            ]);
        Route::patch('material/{id}/out-of-stock', [MaterialController::class, 'outOfStock'])
            ->name('material.out-of-stock')
            ->middleware('can:master.material.update');

        Route::resource('menu-category', MenuCategoryController::class)
            ->except(['show'])
            ->middleware([
                'can:master.menu-category.view',
                'can:master.menu-category.create',
                'can:master.menu-category.update',
                'can:master.menu-category.delete',
            ]);

        Route::resource('menu', MenuController::class)
            ->middleware([
                'can:master.menu.view',
                'can:master.menu.create',
                'can:master.menu.update',
                'can:master.menu.delete',
            ]);
        Route::patch('menu/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])
            ->name('menu.toggle-status')
            ->middleware('can:master.menu.update');

        Route::resource('gallery', GalleryController::class)
            ->except(['show'])
            ->middleware([
                'can:master.gallery.view',
                'can:master.gallery.create',
                'can:master.gallery.update',
                'can:master.gallery.delete',
            ]);

        Route::resource('semi-finished-material', SemiFinishedMaterialController::class)
            ->except(['show'])->middleware('can:master.material.view');
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {

        // User Management - User
        Route::resource('user', ManageUserController::class)
            ->only(['index', 'store', 'destroy'])->middleware('can:settings');

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

        Route::get('role-permission', [RolePermissionController::class, 'index'])
            ->name('role-permission.index')
            ->middleware(['role:Super Admin|Admin', 'can:settings']);
        Route::post('role-permission/update', [RolePermissionController::class, 'update'])
            ->name('role-permission.update')
            ->middleware(['role:Super Admin|Admin', 'can:settings']);
    });

    Route::prefix('management')->name('management.')->group(function () {
        Route::resource('unit-material-converter', UnitMaterialConverterController::class)
            ->only(['index', 'show'])
            ->middleware('can:management.unit-material-converter');
        Route::post('unit-material-converter/{materialId}/converter', [UnitMaterialConverterController::class, 'store'])
            ->name('unit-material-converter.store')
            ->middleware('can:management.unit-material-converter');
        Route::put('unit-material-converter/{materialId}/converter/{converterId}', [UnitMaterialConverterController::class, 'update'])
            ->name('unit-material-converter.update')
            ->middleware('can:management.unit-material-converter');
        Route::delete('unit-material-converter/{materialId}/converter/{converterId}', [UnitMaterialConverterController::class, 'destroy'])
            ->name('unit-material-converter.destroy')
            ->middleware('can:management.unit-material-converter');

        Route::get('inbound-outbound-material', [InboundOutboundMaterialController::class, 'index'])
            ->name('inbound-outbound-material.index')
            ->middleware('can:management.inbound-outbound-material');
        Route::get('inbound-outbound-material/create', [InboundOutboundMaterialController::class, 'create'])
            ->name('inbound-outbound-material.create')
            ->middleware('can:management.inbound-outbound-material');
        Route::post('inbound-outbound-material', [InboundOutboundMaterialController::class, 'store'])
            ->name('inbound-outbound-material.store')
            ->middleware('can:management.inbound-outbound-material');
        Route::get('inbound-outbound-material/materials-by-cafe', [InboundOutboundMaterialController::class, 'getMaterialsByCafe'])
            ->name('inbound-outbound-material.materials-by-cafe')
            ->middleware('can:management.inbound-outbound-material');
        Route::get('inbound-outbound-material/check-unit-converter', [InboundOutboundMaterialController::class, 'checkUnitConverter'])
            ->name('inbound-outbound-material.check-unit-converter')
            ->middleware('can:management.inbound-outbound-material');
        Route::get('inbound-outbound-material/create-outbound', [InboundOutboundMaterialController::class, 'createOutbound'])
            ->name('inbound-outbound-material.create-outbound')
            ->middleware('can:management.inbound-outbound-material');
        Route::post('inbound-outbound-material/store-outbound', [InboundOutboundMaterialController::class, 'storeOutbound'])
            ->name('inbound-outbound-material.store-outbound')
            ->middleware('can:management.inbound-outbound-material');
        Route::get('inbound-outbound-material/{id}/edit', [InboundOutboundMaterialController::class, 'edit'])
            ->name('inbound-outbound-material.edit')
            ->middleware('can:management.inbound-outbound-material');
        Route::put('inbound-outbound-material/{id}', [InboundOutboundMaterialController::class, 'update'])
            ->name('inbound-outbound-material.update')
            ->middleware('can:management.inbound-outbound-material');
        
        // Expense (Pengeluaran)
        Route::get('expense', [ExpenseController::class, 'index'])
            ->name('expense.index')
            ->middleware('can:transaction.history');
        Route::get('expense/create', [ExpenseController::class, 'create'])
            ->name('expense.create')
            ->middleware('can:transaction.history');
        Route::get('expense/menus-by-cafe', [ExpenseController::class, 'getMenusByCafe'])
            ->name('expense.menus-by-cafe')
            ->middleware('can:transaction.history');
        Route::post('expense/check-availability', [ExpenseController::class, 'checkAvailability'])
            ->name('expense.check-availability')
            ->middleware('can:transaction.history');
        Route::post('expense', [ExpenseController::class, 'store'])
            ->name('expense.store')
            ->middleware('can:transaction.history');
    });

    Route::prefix('transaction')->name('transaction.')->group(function () {
        Route::get('history', [HistoryTransactionController::class, 'index'])
            ->name('history.index')
            ->middleware('can:transaction.history');
        Route::get('history/export', [HistoryTransactionController::class, 'export'])
            ->name('history.export')
            ->middleware('can:transaction.history');
        Route::get('history/{id}', [HistoryTransactionController::class, 'show'])
            ->name('history.show')
            ->middleware('can:transaction.history');

        Route::get('cashier', [CashierController::class, 'index'])
            ->name('cashier.index')
            ->middleware('can:transaction.cashier');
        Route::get('cashier/{id}', [CashierController::class, 'show'])
            ->name('cashier.show')
            ->middleware('can:transaction.cashier');
        Route::patch('cashier/{id}/success', [CashierController::class, 'makeSuccess'])
            ->name('cashier.success')
            ->middleware('can:transaction.cashier');
        Route::patch('cashier/{id}/failed', [CashierController::class, 'makeFailed'])
            ->name('cashier.failed')
            ->middleware('can:transaction.cashier');
        Route::patch('cashier/{id}/success-in-order', [CashierController::class, 'makeSuccessInOrder'])
            ->name('cashier.success-in-order')
            ->middleware('can:transaction.cashier');
        Route::patch('cashier/detail/{id}/success', [CashierController::class, 'makeDetailSuccess'])
            ->name('cashier.detail.success')
            ->middleware('can:transaction.cashier');
        Route::get('cashier/detail/{id}/receipt-data', [CashierController::class, 'detailReceiptData'])
            ->name('cashier.detail.receipt-data')
            ->middleware('can:transaction.cashier');
        Route::get('cashier/{id}/receipt-data', [CashierController::class, 'receiptData'])
            ->name('cashier.receipt-data')
            ->middleware('can:transaction.cashier');
        Route::get('cashier/search-qr/{qr_code}', [CashierController::class, 'searchByQRCode'])
            ->name('search-qr')
            ->middleware('can:transaction.cashier');
    });

    Route::prefix('shortcut')->name('shortcut.')->group(function () {
        Route::get('public-link-generator', [PublicLinkGeneratorController::class, 'index'])
            ->name('public-link-generator.index')
            ->middleware('can:shortcut.public-link-generator');
    });
});

Route::get('for-public/{cafeId}', [PublicController::class, 'forPublic']);
Route::get('for-kitchen/{cafeId}', [PublicController::class, 'forKitchen']);
Route::get('bluetooth-receipt/{id}', [CashierController::class, 'bluetoothReceiptData'])->name('bluetooth.receipt');


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
