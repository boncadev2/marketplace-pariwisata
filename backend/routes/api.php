<?php

use App\Http\Controllers\Api\AccountOrderController;
use App\Http\Controllers\Api\AccountSupportTicketController;
use App\Http\Controllers\Api\AccountWishlistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\GuestVoucherController;
use App\Http\Controllers\Api\InventoryHoldController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductQuoteController;
use App\Http\Controllers\Api\VoucherRedemptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/guest/orders/{publicId}/vouchers', [GuestVoucherController::class, 'show'])->middleware('throttle:10,1');
    Route::post('/staff/vouchers/redeem', [VoucherRedemptionController::class, 'store'])->middleware(['auth:sanctum', 'throttle:30,1']);
    Route::post('/webhooks/payments/sandbox', [PaymentWebhookController::class, 'store']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'profile'])->middleware('auth:sanctum');
    Route::patch('/account/profile', [AuthController::class, 'updateProfile'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::get('/account/wishlist', [AccountWishlistController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/account/wishlist', [AccountWishlistController::class, 'store'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::delete('/account/wishlist/{itemId}', [AccountWishlistController::class, 'destroy'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware(['auth:sanctum', 'throttle:6,1']);
    Route::get('/account/orders', [AccountOrderController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/account/orders/{publicId}', [AccountOrderController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/account/orders/{publicId}/vouchers', [AccountOrderController::class, 'vouchers'])->middleware('auth:sanctum');
    Route::post('/account/orders/claim', [AccountOrderController::class, 'claim'])->middleware(['auth:sanctum', 'throttle:6,1']);
    Route::get('/account/support-tickets', [AccountSupportTicketController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/account/support-tickets/{ticketId}', [AccountSupportTicketController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/account/orders/{publicId}/support-tickets', [AccountSupportTicketController::class, 'store'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/account/support-tickets/{ticketId}/messages', [AccountSupportTicketController::class, 'addMessage'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::get('/account/support-tickets/{ticketId}/attachments/{attachmentId}', [AccountSupportTicketController::class, 'download'])->middleware('auth:sanctum');
    Route::get('/lookup/regions', [LookupController::class, 'regions']);
    Route::get('/lookup/categories', [LookupController::class, 'categories']);
    Route::get('/destinations', [DestinationController::class, 'index']);
    Route::get('/products/{product:slug}/quote', [ProductQuoteController::class, 'show']);
    Route::get('/products/{product:slug}/inventory', [InventoryHoldController::class, 'calendar']);
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/media', [MediaController::class, 'store'])->middleware('auth:sanctum');
    
    // Phase 24: Pembatalan dan refund
    Route::post('/orders/{order}/refunds', [\App\Http\Controllers\Api\V1\RefundRequestController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/refunds/{refundRequest}/approve', [\App\Http\Controllers\Api\V1\RefundRequestController::class, 'approve'])->middleware('auth:sanctum');
    Route::post('/refunds/{refundRequest}/reject', [\App\Http\Controllers\Api\V1\RefundRequestController::class, 'reject'])->middleware('auth:sanctum');
    
    // Phase 25: Laporan Keuangan
    Route::get('/revenue-reports', [\App\Http\Controllers\Api\V1\RevenueReportingController::class, 'index'])->middleware('auth:sanctum');

    // Phase 26: Payout and Settlement
    Route::get('/payouts/eligible', [\App\Http\Controllers\Api\V1\PayoutController::class, 'eligible']);
    Route::post('/payouts/batches', [\App\Http\Controllers\Api\V1\PayoutController::class, 'storeBatch']);
    Route::post('/payouts/batches/{batch}/approve', [\App\Http\Controllers\Api\V1\PayoutController::class, 'approveBatch']);
    Route::post('/payouts/batches/{batch}/process', [\App\Http\Controllers\Api\V1\PayoutController::class, 'processBatch']);
    Route::post('/payouts/batches/{batch}/complete', [\App\Http\Controllers\Api\V1\PayoutController::class, 'completeBatch']);
    Route::get('/partner-bank-accounts', [\App\Http\Controllers\Api\V1\PartnerBankAccountController::class, 'index']);
    Route::post('/partner-bank-accounts', [\App\Http\Controllers\Api\V1\PartnerBankAccountController::class, 'store']);
    Route::post('/partner-bank-accounts/{account}/verify', [\App\Http\Controllers\Api\V1\PartnerBankAccountController::class, 'verify']);
    // Phase 27: Operational Disputes and Reviews
    Route::apiResource("disputes", \App\Http\Controllers\Api\V1\OperationalDisputeController::class)->only(["index", "store", "update"]);
    Route::apiResource("reviews", \App\Http\Controllers\Api\V1\ReviewController::class)->only(["index", "store", "show"]);

    // Phase 28: Reconciliation
    Route::post('/reconciliation', [\App\Http\Controllers\Api\ReconciliationController::class, 'store']);
});
