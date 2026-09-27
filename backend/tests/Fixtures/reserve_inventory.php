<?php

use App\Exceptions\InventoryUnavailableException;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\InventoryBucket;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$start = (float) $argv[2];
while (microtime(true) < $start) {
    usleep(1000);
}

if (($argv[3] ?? 'reserve') === 'paid') {
    (new ProcessPaymentWebhook((int) $argv[1]))->handle();
    echo 'processed';
    exit;
}
if (($argv[3] ?? 'reserve') === 'expire') {
    app(InventoryReservationService::class)->releaseExpired();
    echo 'expired';
    exit;
}

try {
    $bucket = InventoryBucket::findOrFail((int) $argv[1]);
    app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
    echo 'reserved';
} catch (InventoryUnavailableException) {
    echo 'unavailable';
}
