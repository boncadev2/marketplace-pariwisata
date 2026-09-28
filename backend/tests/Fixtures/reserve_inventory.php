<?php

use App\Exceptions\InventoryUnavailableException;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\InventoryBucket;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ExpireOrderPayments;
use App\Services\InventoryReservationService;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
