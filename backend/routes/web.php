<?php

use App\Mail\TransactionNotice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dev/notifications/{type}', function (string $type) {
    abort_unless(app()->environment(['local', 'testing']), 404);
    abort_unless(array_key_exists($type, TransactionNotice::TEMPLATES), 404);

    return response((new TransactionNotice($type, [
        'order_id' => 'DEMO-PREVIEW-001',
        'name' => 'Pelanggan demonstrasi',
        'detail' => 'PREVIEW — Data demonstrasi. Tidak ada email yang dikirim atau transaksi yang diproses.',
    ]))->render())->header('Cache-Control', 'private, no-store');
});
