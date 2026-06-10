<?php

use App\Http\Controllers\Api\CheckMenuAvailableController;
use App\Http\Controllers\Api\CheckMenusAvailableController;
use App\Http\Controllers\Api\CheckTransactionStatusController;
use App\Http\Controllers\Api\GetMenuCafeTableController;
use App\Http\Controllers\Api\CreateOpenBillController;
use App\Http\Controllers\Api\AddOrderOpenBillController;
use App\Http\Controllers\Api\CheckPromoCodeController;
use App\Http\Controllers\Api\MakeFailedOpenBillController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PayOpenbillQrisController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Dashboard\PublicController;
use Illuminate\Support\Facades\Route;

// Route::middleware('guest')->group(function () {
    Route::get('/queue/public/{cafeId}', [PublicController::class, 'apiPublicQueue']);
    Route::get('/queue/kitchen/{cafeId}', [PublicController::class, 'apiKitchenQueue']);
    
    Route::get('/landing-page', LandingPageController::class);
    Route::get('/get-menu-cafe-table', GetMenuCafeTableController::class);

    Route::post('/check-available-materials', CheckMenuAvailableController::class);
    Route::post('/check-available-materials/bulk', CheckMenusAvailableController::class);

    Route::post('/create-open-bill', CreateOpenBillController::class);
    Route::post('/add-order-open-bill', AddOrderOpenBillController::class);
    Route::post('/make-failed-open-bill', MakeFailedOpenBillController::class);

    Route::post('/make-transaction', TransactionController::class);
    Route::post('/payment-webhook', PaymentWebhookController::class);
    Route::get('/transaction/{transaction}/status', CheckTransactionStatusController::class);

    Route::post('/pay-openbill-qris', PayOpenbillQrisController::class);

    Route::post('/check-promo-code', CheckPromoCodeController::class);
// });