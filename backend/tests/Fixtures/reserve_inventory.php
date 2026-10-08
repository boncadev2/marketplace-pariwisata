<?php

use App\Exceptions\InventoryUnavailableException;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use App\Services\CrossVillageOrderSnapshotService;
use App\Services\ExpireOrderPayments;
use App\Services\InventoryReservationService;
use App\Services\LodgingReservationService;
use App\Services\MealReservationService;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'wisata_concurrency_test',
]);
if (getenv('DB_HOST') || (isset($_SERVER['DB_HOST']) && ! empty($_SERVER['DB_HOST']))) {
    config(['database.connections.mysql.host' => getenv('DB_HOST') ?: $_SERVER['DB_HOST']]);
}
if (getenv('APP_KEY') || (isset($_SERVER['APP_KEY']) && ! empty($_SERVER['APP_KEY']))) {
    config(['app.key' => getenv('APP_KEY') ?: $_SERVER['APP_KEY']]);
}
$start = (float) $argv[2];
while (microtime(true) < $start) {
    usleep(1000);
}

if (($argv[3] ?? '') === 'cross-village-checkout') {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
        throw new RuntimeException('Checkout race tests require the isolated concurrency database.');
    }
    [$order] = app(CheckoutService::class)->create(Product::findOrFail((int) $argv[1]), CarbonImmutable::parse($argv[6]),
        1, 'Snapshot race', 'snapshot-race@example.test', $argv[4], null, null, 10001, $argv[5]);
    echo $order->public_id;
    exit;
}

if (($argv[3] ?? '') === 'cross-village-snapshot') {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
        throw new RuntimeException('Snapshot race tests require the isolated concurrency database.');
    }
    $snapshot = app(CrossVillageOrderSnapshotService::class)->create(
        Order::findOrFail((int) $argv[1]), User::findOrFail((int) $argv[4]), $argv[5], 'Concurrent sandbox simulation', '127.0.0.1');
    echo hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    exit;
}

if (($argv[3] ?? '') === 'coupon') {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
        throw new RuntimeException('Coupon race tests require the isolated concurrency database.');
    }
    $product = Product::findOrFail((int) $argv[1]);
    $user = User::findOrFail((int) $argv[4]);
    try {
        app(CheckoutService::class)->create($product, CarbonImmutable::parse('2026-10-10'), 1, $user->name, $user->email, $argv[5], $argv[6], $user, 9000);
        echo 'redeemed';
    } catch (ValidationException) {
        echo 'unavailable';
    }
    exit;
}

if (in_array($argv[3] ?? '', ['meal', 'meal-cancel'], true)) {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
        throw new RuntimeException('Meal race tests require the isolated concurrency database.');
    }
    $user = User::findOrFail((int) $argv[4]);
    $service = app(MealReservationService::class);
    if ($argv[3] === 'meal-cancel') {
        $service->cancel($user, (int) $argv[1]);
        echo 'cancelled';
        exit;
    }
    try {
        $service->reserve($user, (int) $argv[1], 1, $argv[5], '100.00');
        echo 'reserved';
    } catch (ValidationException) {
        echo 'unavailable';
    }
    exit;
}

if (in_array($argv[3] ?? '', ['lodging', 'lodging-cancel'], true)) {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
        throw new RuntimeException('Lodging race tests require the isolated concurrency database.');
    }
    $user = User::findOrFail((int) $argv[4]);
    $service = app(LodgingReservationService::class);
    if ($argv[3] === 'lodging-cancel') {
        $service->cancel($user, (int) $argv[1]);
        echo 'cancelled';
        exit;
    }
    try {
        $service->reserve($user, [
            'room_type_id' => (int) $argv[1], 'check_in' => '2026-10-10', 'check_out' => '2026-10-12', 'quantity' => 1, 'guests' => 1,
        ], $argv[5], '200.00');
        echo 'reserved';
    } catch (ValidationException) {
        echo 'unavailable';
    }
    exit;
}

if (($argv[3] ?? 'reserve') === 'paid') {
    (new ProcessPaymentWebhook((int) $argv[1]))->handle();
    echo 'processed';
    exit;
}
if (($argv[3] ?? 'reserve') === 'expire') {
    app(InventoryReservationService::class)->releaseExpired();
    app(ExpireOrderPayments::class)->run();
    echo 'expired';
    exit;
}
if (($argv[3] ?? 'reserve') === 'redeem') {
    try {
        $voucher = Voucher::findOrFail((int) $argv[1]);
        $staff = User::findOrFail((int) $argv[4]);
        app(VoucherService::class)->redeem($voucher->token, $staff);
        echo 'redeemed';
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 409) {
            throw $exception;
        }
        echo 'already_used';
    }
    exit;
}

try {
    $bucket = InventoryBucket::findOrFail((int) $argv[1]);
    app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
    echo 'reserved';
} catch (InventoryUnavailableException) {
    echo 'unavailable';
}
