<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\InventoryHoldController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductQuoteController;
use App\Http\Controllers\Api\VoucherRedemptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/staff/vouchers/redeem', [VoucherRedemptionController::class, 'store'])->middleware(['auth:sanctum', 'throttle:30,1']);
    Route::post('/webhooks/payments/sandbox', [PaymentWebhookController::class, 'store']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'profile'])->middleware('auth:sanctum');
    Route::get('/lookup/regions', [LookupController::class, 'regions']);
    Route::get('/lookup/categories', [LookupController::class, 'categories']);
    Route::get('/destinations', [DestinationController::class, 'index']);
    Route::get('/products/{product:slug}/quote', [ProductQuoteController::class, 'show']);
    Route::get('/products/{product:slug}/inventory', [InventoryHoldController::class, 'calendar']);
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/media', [MediaController::class, 'store'])->middleware('auth:sanctum');
});
