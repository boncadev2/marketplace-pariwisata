<?php

use App\Mail\TransactionNotice;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id, string $hash) {
    if ($request->user() && (string) $request->user()->getKey() !== (string) $id) {
        abort(403);
    }

    $user = User::findOrFail($id);

    if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
        abort(403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    Auth::guard('web')->login($user);
    $request->session()->regenerate();

    return redirect(config('services.frontend_url').'/akun');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::get('/login', fn () => redirect(config('services.frontend_url').'/login'))->name('login');

Route::get('/dev/notifications/{type}', function (string $type) {
    abort_unless(app()->environment(['local', 'testing']), 404);
    abort_unless(array_key_exists($type, TransactionNotice::TEMPLATES), 404);

    return response((new TransactionNotice($type, [
        'order_id' => 'DEMO-PREVIEW-001',
        'name' => 'Pelanggan demonstrasi',
        'detail' => 'PREVIEW — Data demonstrasi. Tidak ada email yang dikirim atau transaksi yang diproses.',
    ]))->render())->header('Cache-Control', 'private, no-store');
});
