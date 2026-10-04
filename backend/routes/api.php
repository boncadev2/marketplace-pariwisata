<?php

use App\Http\Controllers\Api\AccountDataDeletionController;
use App\Http\Controllers\Api\AccountOrderController;
use App\Http\Controllers\Api\AccountSupportTicketController;
use App\Http\Controllers\Api\AccountWishlistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CouponQuoteController;
use App\Http\Controllers\Api\CrossVillageAgreementController;
use App\Http\Controllers\Api\CrossVillageConfigurationController;
use App\Http\Controllers\Api\CrossVillageOrderSnapshotController;
use App\Http\Controllers\Api\CrossVillageQuoteController;
use App\Http\Controllers\Api\CulinaryManagementController;
use App\Http\Controllers\Api\CulinaryPhotoController;
use App\Http\Controllers\Api\CulinaryPlaceController;
use App\Http\Controllers\Api\CulinaryScheduleController;
use App\Http\Controllers\Api\DependencyHealthController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\GuestVoucherController;
use App\Http\Controllers\Api\InventoryHoldController;
use App\Http\Controllers\Api\LodgingBookingController;
use App\Http\Controllers\Api\LodgingCalendarController;
use App\Http\Controllers\Api\LodgingManagementController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MealBookingController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\PackageCalendarController;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\PaymentGatewayStatusController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PilotCheckoutControlController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ProductionReadinessController;
use App\Http\Controllers\Api\ProductQuoteController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RefundManagementController;
use App\Http\Controllers\Api\ReservationPaymentController;
use App\Http\Controllers\Api\SecurityConfirmationController;
use App\Http\Controllers\Api\ServiceReservationConfirmationController;
use App\Http\Controllers\Api\ServiceReservationController;
use App\Http\Controllers\Api\TourPackageDetailController;
use App\Http\Controllers\Api\TravelCatalogManagementController;
use App\Http\Controllers\Api\TravelPhotoController;
use App\Http\Controllers\Api\UmkmOrderController;
use App\Http\Controllers\Api\UmkmProductController;
use App\Http\Controllers\Api\UmkmProductManagementController;
use App\Http\Controllers\Api\UmkmShippingController;
use App\Http\Controllers\Api\V1\OperationalDashboardController;
use App\Http\Controllers\Api\V1\OperationalDisputeController;
use App\Http\Controllers\Api\V1\PartnerBankAccountController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\RefundRequestController;
use App\Http\Controllers\Api\V1\RevenueReportingController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\VoucherRedemptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/dependencies', DependencyHealthController::class)->middleware('throttle:30,1');
    Route::get('/pilot/checkout-status', [PilotCheckoutControlController::class, 'show'])->middleware('throttle:30,1');
    Route::patch('/pilot/checkout-status', [PilotCheckoutControlController::class, 'update'])
        ->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::get('/guest/orders/{publicId}/vouchers', [GuestVoucherController::class, 'show'])->middleware('throttle:10,1');
    Route::post('/staff/vouchers/redeem', [VoucherRedemptionController::class, 'store'])->middleware(['auth:sanctum', 'throttle:30,1']);
    Route::get('/dashboard/production-readiness', ProductionReadinessController::class)->middleware(['auth:sanctum', 'admin.platform', 'throttle:20,1']);
    Route::get('/payments/gateway-status', PaymentGatewayStatusController::class)->middleware('throttle:30,1');
    Route::post('/webhooks/payments/midtrans', MidtransWebhookController::class)->middleware('throttle:webhooks');
    Route::post('/webhooks/payments/sandbox', [PaymentWebhookController::class, 'store'])->middleware('throttle:webhooks');
    Route::get('/promos/quote', CouponQuoteController::class)->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::post('/account/reservation-payments/{kind}/{booking}/{action}', ReservationPaymentController::class)->whereIn('kind', ['umkm', 'lodging', 'culinary'])->whereIn('action', ['checkout', 'refresh', 'cancel'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/security/confirm-password', [SecurityConfirmationController::class, 'store'])->middleware(['auth:sanctum', 'throttle:sensitive-confirmation']);
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
    Route::get('/account/data-deletion-request', [AccountDataDeletionController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/account/data-deletion-request', [AccountDataDeletionController::class, 'store'])->middleware(['auth:sanctum', 'throttle:privacy']);
    Route::get('/dashboard/culinary-places/{place}/slots', [CulinaryScheduleController::class, 'index'])->whereNumber('place')->middleware(['auth:sanctum', 'service.management']);
    Route::post('/dashboard/culinary-places/{place}/slots', [CulinaryScheduleController::class, 'store'])->whereNumber('place')->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::patch('/dashboard/culinary-places/{place}/slots/{slot}', [CulinaryScheduleController::class, 'update'])->whereNumber(['place', 'slot'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::post('/dashboard/service-reservations/{type}/{booking}/confirm', ServiceReservationConfirmationController::class)->whereIn('type', ['lodging', 'culinary'])->whereNumber('booking')->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::post('/dashboard/service-reservations/{type}/{booking}/{action}', ServiceReservationConfirmationController::class)->whereIn('type', ['lodging', 'culinary'])->whereNumber('booking')->whereIn('action', ['check-in', 'complete', 'cancel'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/dashboard/service-reservations', ServiceReservationController::class)->middleware(['auth:sanctum', 'service.management', 'throttle:60,1']);
    Route::post('/dashboard/culinary-places/{place}/photo', [CulinaryPhotoController::class, 'store'])->whereNumber('place')->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/culinary/places/{place}/photo', [CulinaryPhotoController::class, 'show'])->whereNumber('place');
    Route::post('/dashboard/culinary-places', [CulinaryManagementController::class, 'store'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/dashboard/culinary-places', [CulinaryManagementController::class, 'index'])->middleware(['auth:sanctum', 'service.management']);
    Route::patch('/dashboard/culinary-places/{place}', [CulinaryManagementController::class, 'update'])->whereNumber('place')->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/culinary/places', [CulinaryPlaceController::class, 'index']);
    Route::get('/culinary/places/{place}', [CulinaryPlaceController::class, 'show'])->whereNumber('place');
    Route::get('/culinary/places/{place}/slots', [CulinaryPlaceController::class, 'slots'])->whereNumber('place')->middleware('throttle:60,1');
    Route::get('/culinary/slots', [MealBookingController::class, 'slots']);
    Route::get('/culinary/quote', [MealBookingController::class, 'quote'])->middleware('throttle:60,1');
    Route::get('/account/meal-bookings', [MealBookingController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/account/meal-bookings', [MealBookingController::class, 'store'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/account/meal-bookings/{booking}/cancel', [MealBookingController::class, 'cancel'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::get('/dashboard/lodging-rooms/{room}/calendar', [LodgingCalendarController::class, 'index'])->middleware(['auth:sanctum', 'service.management', 'throttle:60,1']);
    Route::patch('/dashboard/lodging-rooms/{room}/calendar', [LodgingCalendarController::class, 'update'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::post('/dashboard/lodging-rooms', [LodgingManagementController::class, 'store'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/dashboard/lodging-rooms', [LodgingManagementController::class, 'index'])->middleware(['auth:sanctum', 'service.management']);
    Route::patch('/dashboard/lodging-rooms/{room}', [LodgingManagementController::class, 'update'])->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/lodging/rooms/{room}/photos/{kind}', [LodgingBookingController::class, 'photo']);
    Route::get('/lodging/rooms/{room}', [LodgingBookingController::class, 'show'])->whereNumber('room');
    Route::get('/lodging/rooms', [LodgingBookingController::class, 'rooms']);
    Route::get('/lodging/quote', [LodgingBookingController::class, 'quote'])->middleware('throttle:60,1');
    Route::get('/account/lodging-bookings', [LodgingBookingController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/account/lodging-bookings', [LodgingBookingController::class, 'store'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/account/lodging-bookings/{booking}/cancel', [LodgingBookingController::class, 'cancel'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::get('/lookup/regions', [LookupController::class, 'regions']);
    Route::get('/lookup/categories', [LookupController::class, 'categories']);
    Route::get('/dashboard/packages/{product}/calendar', [PackageCalendarController::class, 'index'])->whereNumber('product')->middleware(['auth:sanctum', 'service.management', 'throttle:60,1']);
    Route::patch('/dashboard/packages/{product}/calendar', [PackageCalendarController::class, 'update'])->whereNumber('product')->middleware(['auth:sanctum', 'service.management', 'throttle:20,1']);
    Route::get('/tour-packages/{product:slug}', TourPackageDetailController::class);
    Route::prefix('dashboard/travel')->middleware(['auth:sanctum', 'service.management', 'throttle:30,1'])->group(function (): void {
        Route::get('/options', [TravelCatalogManagementController::class, 'options']);
        Route::get('/{kind}', [TravelCatalogManagementController::class, 'index'])->whereIn('kind', ['destinations', 'packages']);
        Route::post('/{kind}', [TravelCatalogManagementController::class, 'save'])->whereIn('kind', ['destinations', 'packages']);
        Route::patch('/{kind}/{id}', [TravelCatalogManagementController::class, 'save'])->whereIn('kind', ['destinations', 'packages'])->whereNumber('id');
    });
    Route::get('/travel/photos/{photo}', [TravelPhotoController::class, 'show'])->name('travel.photos.show');
    Route::prefix('dashboard/travel/{kind}/{id}/photos')->whereIn('kind', ['destinations', 'packages'])->whereNumber('id')->middleware(['auth:sanctum', 'service.management', 'throttle:uploads'])->group(function (): void {
        Route::get('/', [TravelPhotoController::class, 'index']);
        Route::post('/', [TravelPhotoController::class, 'store']);
        Route::delete('/{photo}', [TravelPhotoController::class, 'destroy'])->whereNumber('photo');
    });
    Route::get('/destinations', [DestinationController::class, 'index']);
    Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show']);
    Route::get('/destinations/{destination:slug}/packages', [DestinationController::class, 'packages']);
    Route::get('/dashboard/umkm-products/{slug}/photo', [UmkmProductManagementController::class, 'photo'])->middleware(['auth:sanctum', 'throttle:60,1']);
    Route::get('/umkm-products/{slug}/photo', [UmkmProductController::class, 'photo'])->middleware('throttle:60,1');
    Route::get('/dashboard/umkm-products', [UmkmProductManagementController::class, 'index'])->middleware(['auth:sanctum', 'throttle:60,1']);
    Route::post('/dashboard/umkm-products', [UmkmProductManagementController::class, 'store'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::patch('/dashboard/umkm-products/{slug}', [UmkmProductManagementController::class, 'update'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::get('/dashboard/umkm-orders', [UmkmOrderController::class, 'partnerIndex'])->middleware(['auth:sanctum', 'throttle:60,1']);
    Route::patch('/dashboard/umkm-orders/{publicId}', [UmkmOrderController::class, 'partnerUpdate'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::get('/account/umkm-orders', [UmkmOrderController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/account/umkm-orders', [UmkmOrderController::class, 'store'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/account/umkm-orders/{publicId}/cancel', [UmkmOrderController::class, 'cancel'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::get('/umkm-products', [UmkmProductController::class, 'index']);
    Route::get('/umkm-products/{slug}', [UmkmProductController::class, 'show']);
    Route::post('/account/umkm-shipping/quotes', [UmkmShippingController::class, 'quote'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::get('/account/umkm-orders/{publicId}/tracking', [UmkmShippingController::class, 'tracking'])->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::get('/products', [ProductCatalogController::class, 'index']);
    Route::get('/products/{product:slug}/quote', [ProductQuoteController::class, 'show']);
    Route::get('/partner/cross-village-proposals', [CrossVillageAgreementController::class, 'index'])
        ->middleware(['auth:sanctum', 'throttle:30,1']);
    Route::get('/products/{product:slug}/cross-village-agreement', [CrossVillageAgreementController::class, 'show'])
        ->middleware(['auth:sanctum', 'throttle:30,1']);
    Route::post('/products/{product:slug}/cross-village-agreement', [CrossVillageAgreementController::class, 'store'])
        ->middleware(['auth:sanctum', 'sensitive.confirmed', 'throttle:20,1']);
    Route::get('/orders/{order:public_id}/cross-village-snapshot', [CrossVillageOrderSnapshotController::class, 'show'])
        ->middleware(['auth:sanctum', 'admin.platform', 'throttle:20,1']);
    Route::post('/orders/{order:public_id}/cross-village-snapshot', [CrossVillageOrderSnapshotController::class, 'store'])
        ->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:20,1']);
    Route::get('/products/{product:slug}/cross-village-configuration', [CrossVillageConfigurationController::class, 'show'])
        ->middleware(['auth:sanctum', 'admin.platform', 'throttle:20,1']);
    Route::put('/products/{product:slug}/cross-village-configuration', [CrossVillageConfigurationController::class, 'update'])
        ->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:20,1']);
    Route::get('/products/{product:slug}/cross-village-quote', CrossVillageQuoteController::class)
        ->middleware(['auth:sanctum', 'admin.platform', 'throttle:20,1']);
    Route::get('/products/{product:slug}/inventory', [InventoryHoldController::class, 'calendar']);
    Route::get('/partner-applications', [PartnerApplicationController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store'])->middleware(['auth:sanctum', 'throttle:6,1']);
    Route::get('/dashboard/partner-applications', [PartnerApplicationController::class, 'adminIndex'])->middleware(['auth:sanctum', 'admin.platform']);
    Route::post('/dashboard/partner-applications/{partner}/decision', [PartnerApplicationController::class, 'decide'])->whereNumber('partner')->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:20,1']);
    Route::post('/media', [MediaController::class, 'store'])->middleware(['auth:sanctum', 'throttle:uploads']);

    Route::post('/privacy/data-deletion-requests/{dataDeletionRequest}/process', [AccountDataDeletionController::class, 'process'])
        ->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:privacy']);

    Route::get('/dashboard/refunds', [RefundManagementController::class, 'index'])->middleware(['auth:sanctum', 'admin.platform']);
    Route::match(['get', 'post'], '/account/reservation-payments/{reference}/refund', [RefundManagementController::class, 'reservation'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::match(['get', 'post'], '/account/orders/{publicId}/refund', [RefundManagementController::class, 'order'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/dashboard/reservation-refunds/{refund}/{action}', [RefundManagementController::class, 'decide'])->whereNumber('refund')->whereIn('action', ['approve', 'reject'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:10,1']);
    Route::post('/dashboard/refunds/{kind}/{refund}/refresh', [RefundManagementController::class, 'refresh'])->whereNumber('refund')->whereIn('kind', ['order', 'reservation'])->middleware(['auth:sanctum', 'admin.platform', 'throttle:10,1']);
    // Phase 24: Pembatalan dan refund
    Route::post('/orders/{order}/refunds', [RefundRequestController::class, 'store'])->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/refunds/{refundRequest}/approve', [RefundRequestController::class, 'approve'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:10,1']);
    Route::post('/refunds/{refundRequest}/reject', [RefundRequestController::class, 'reject'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:10,1']);

    // Phase 25: Laporan Keuangan
    Route::get('/revenue-reports', [RevenueReportingController::class, 'index'])->middleware(['auth:sanctum', 'admin.platform']);

    // Phase 26: Payout and Settlement
    Route::get('/payouts/eligible', [PayoutController::class, 'eligible'])->middleware(['auth:sanctum', 'admin.platform']);
    Route::post('/payouts/batches', [PayoutController::class, 'storeBatch'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::post('/payouts/batches/{batch}/approve', [PayoutController::class, 'approveBatch'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::post('/payouts/batches/{batch}/process', [PayoutController::class, 'processBatch'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::post('/payouts/batches/{batch}/complete', [PayoutController::class, 'completeBatch'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::get('/partner-bank-accounts', [PartnerBankAccountController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/partner-bank-accounts', [PartnerBankAccountController::class, 'store'])->middleware(['auth:sanctum', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    Route::post('/partner-bank-accounts/{account}/verify', [PartnerBankAccountController::class, 'verify'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed', 'throttle:sensitive-confirmation']);
    // Operational disputes and reviews
    Route::get('/disputes', [OperationalDisputeController::class, 'index'])->middleware(['auth:sanctum', 'admin.platform']);
    Route::post('/disputes', [OperationalDisputeController::class, 'store'])->middleware('auth:sanctum');
    Route::patch('/disputes/{operationalDispute}', [OperationalDisputeController::class, 'update'])->middleware(['auth:sanctum', 'admin.platform', 'sensitive.confirmed']);
    Route::apiResource('reviews', ReviewController::class)->only(['store'])->middleware('auth:sanctum');
    Route::apiResource('reviews', ReviewController::class)->only(['index', 'show']);

    // Phase 27: Dashboard operasional admin dan mitra
    Route::prefix('dashboard')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
        Route::get('/summary', [OperationalDashboardController::class, 'summary']);
        Route::get('/transactions', [OperationalDashboardController::class, 'transactions']);
        Route::get('/transactions/{order}', [OperationalDashboardController::class, 'transaction']);
        Route::get('/exceptions', [OperationalDashboardController::class, 'exceptions']);
        Route::get('/export', [OperationalDashboardController::class, 'export'])->middleware('throttle:10,1');
    });

    // Phase 28: Rekonsiliasi otomatis dan recovery transaksi
    Route::prefix('reconciliation')->middleware(['auth:sanctum', 'admin.platform', 'throttle:60,1'])->group(function (): void {
        Route::get('/reports', [ReconciliationController::class, 'reports']);
        Route::get('/alerts', [ReconciliationController::class, 'alerts']);
        Route::post('/attempts/{paymentAttempt}/retry', [ReconciliationController::class, 'retry'])->middleware('sensitive.confirmed');
        Route::patch('/alerts/{reconciliationAlert}/resolve', [ReconciliationController::class, 'resolve'])->middleware('sensitive.confirmed');
        Route::post('/', [ReconciliationController::class, 'store'])->middleware('sensitive.confirmed');
    });
});
