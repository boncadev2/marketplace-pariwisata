<?php

use App\Http\Controllers\Api\UmkmOrderController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'wisata_concurrency_test') {
    exit(2);
}
while (microtime(true) < (float) $argv[1]) {
    usleep(1000);
}
$request = Request::create('/', 'POST', ['product_slug' => $argv[2], 'quantity' => 1, 'expected_price' => 45000, 'customer_name' => 'Race Demo', 'customer_phone' => '081234567890']);
$request->headers->set('Idempotency-Key', $argv[4]);
$request->setUserResolver(fn () => User::findOrFail($argv[3]));
try {
    $response = app(UmkmOrderController::class)->store($request);
    echo $response->getStatusCode();
} catch (HttpException $error) {
    echo $error->getStatusCode();
}
