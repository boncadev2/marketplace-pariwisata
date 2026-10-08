<?php

use App\Models\UmkmOrder;
use App\Models\User;
use App\Services\ReservationPaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'wisata_concurrency_test') {
    exit(2);
}
config(['services.midtrans.server_key' => 'SB-Mid-server-testkey']);
Http::preventStrayRequests();
$sent = 0;
Http::fake(function () use (&$sent) {
    $sent++;

    return Http::response(['token' => 'sandbox-token-123456789', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/sandbox-token-123456789']);
});
while (microtime(true) < (float) $argv[1]) {
    usleep(1000);
}
$order = UmkmOrder::findOrFail($argv[2]);
app(ReservationPaymentService::class)->checkout('umkm', $order, User::findOrFail($order->user_id));
echo $sent;
