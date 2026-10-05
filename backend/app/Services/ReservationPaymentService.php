<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\ReservationPayment;
use App\Models\RoomType;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Payments\MidtransSandboxGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ReservationPaymentService
{
    public function __construct(private MidtransSandboxGateway $gateway) {}

    public function owned(string $kind, string $id, User $user): Model
    {
        $model = $this->model($kind);

        return $model::query()->where('user_id', $user->id)->where($kind === 'umkm' ? 'public_id' : 'id', $id)->firstOrFail();
    }

    public function checkout(string $kind, Model $subject, User $user): ReservationPayment
    {
        $this->gateway->assertConfigured();

        return Cache::lock('reservation-pay:'.$kind.':'.$subject->id, 60)->block(5, function () use ($kind, $subject, $user): ReservationPayment {
            [$payment, $created] = DB::transaction(function () use ($kind, $subject, $user): array {
                $booking = $this->lockSubject($kind, $subject->id);
                abort_unless($booking->user_id === $user->id, 404);
                $existing = $booking->reservationPayment()->lockForUpdate()->first();
                if ($existing) {
                    return [$existing, false];
                }
                abort_unless($booking->status === 'reserved_sandbox' && $booking->getAttribute('checked_in_at') === null && $booking->getAttribute('completed_at') === null, 409, 'Pesanan ini tidak menerima pembayaran baru.');
                if ($kind === 'lodging') {
                    abort_if($booking->check_in->toDateString() < now('Asia/Jakarta')->toDateString(), 409, 'Tanggal menginap sudah lewat.');
                } elseif ($kind === 'culinary') {
                    abort_if($booking->time_slot->lessThanOrEqualTo(now()), 409, 'Jadwal makan sudah lewat.');
                }
                $raw = (string) ($kind === 'umkm' ? $booking->total : $booking->total_price);
                abort_unless(preg_match('/^([0-9]{1,13})(?:\.00)?$/D', $raw, $parts) === 1 && (int) $parts[1] > 0, 422, 'Midtrans memerlukan nominal rupiah bulat dan lebih dari nol.');
                $payment = ReservationPayment::create(['provider' => $this->gateway->provider(), 'kind' => $kind, 'booking_id' => $booking->id, 'user_id' => $user->id, 'reference' => Str::uuid()->toString(), 'amount' => (int) $parts[1], 'status' => 'created', 'expires_at' => now()->addMinutes(15)]);

                return [$payment, true];
            }, 3);
            if (! $created) {
                return $payment;
            }
            try {
                $result = $this->gateway->createReservationCheckout($payment, $user);

                return DB::transaction(function () use ($payment, $result): ReservationPayment {
                    $locked = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
                    $changes = ['checkout_url' => $result['checkout_url'], 'snap_token' => $result['snap_token']];
                    if ($locked->status === 'created') {
                        $changes['status'] = 'pending';
                    }
                    $locked->update($changes);

                    return $locked;
                }, 3);
            } catch (Throwable) {
                ReservationPayment::query()->whereKey($payment->id)->where('status', 'created')->update(['status' => 'uncertain']);

                return $payment->fresh();
            }
        });
    }

    public function changeMethod(string $kind, Model $subject, User $user): ReservationPayment
    {
        $this->gateway->assertConfigured();

        return Cache::lock('reservation-pay:'.$kind.':'.$subject->id, 60)->block(5, function () use ($kind, $subject, $user): ReservationPayment {
            $payment = DB::transaction(function () use ($kind, $subject, $user): ReservationPayment {
                $booking = $this->lockSubject($kind, $subject->id);
                abort_unless($booking->user_id === $user->id, 404);
                $existing = $booking->reservationPayment()->lockForUpdate()->first();
                abort_unless($existing !== null, 404, 'Pembayaran belum dibuat.');
                abort_if(in_array($existing->status, ['paid', 'refunded', 'payment_exception'], true), 409, 'Pembayaran sudah selesai atau sedang ditangani admin.');

                if ($existing->snap_token || $existing->reference) {
                    try {
                        $this->gateway->cancelReservationCheckout($existing);
                    } catch (Throwable) {
                        // Ignore if already canceled or expired in Midtrans
                    }
                }

                $existing->update([
                    'reference' => Str::uuid()->toString(),
                    'status' => 'created',
                    'checkout_url' => null,
                    'snap_token' => null,
                    'provider_status' => null,
                    'expires_at' => now()->addMinutes(15),
                    'last_checked_at' => null,
                ]);

                return $existing->fresh();
            }, 3);

            try {
                $result = $this->gateway->createReservationCheckout($payment, $user);

                return DB::transaction(function () use ($payment, $result): ReservationPayment {
                    $locked = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
                    $changes = ['checkout_url' => $result['checkout_url'], 'snap_token' => $result['snap_token']];
                    if ($locked->status === 'created') {
                        $changes['status'] = 'pending';
                    }
                    $locked->update($changes);

                    return $locked;
                }, 3);
            } catch (Throwable) {
                ReservationPayment::query()->whereKey($payment->id)->where('status', 'created')->update(['status' => 'uncertain']);

                return $payment->fresh();
            }
        });
    }

    public function refresh(ReservationPayment $payment): ReservationPayment
    {
        abort_unless($payment->provider === $this->gateway->provider(), 409, 'Pembayaran berasal dari lingkungan Midtrans berbeda.');
        $actual = $this->gateway->status($payment->reference);
        if ($actual === null) {
            if ($payment->expires_at->isPast() && $payment->snap_token && ! in_array($payment->status, ['paid', 'refunded', 'failed', 'payment_exception'], true)) {
                return $this->apply($payment, $this->gateway->cancelReservationCheckout($payment));
            }
            $payment->update(['last_checked_at' => now()]);

            return $payment->fresh();
        }

        return $this->apply($payment, $actual);
    }

    public function cancel(ReservationPayment $payment): ReservationPayment
    {
        abort_unless($payment->provider === $this->gateway->provider(), 409, 'Pembayaran berasal dari lingkungan Midtrans berbeda.');

        return Cache::lock('reservation-cancel:'.$payment->reference, 60)->block(5, function () use ($payment): ReservationPayment {
            $payment = $this->refresh($payment);
            abort_if(in_array($payment->status, ['paid', 'refunded', 'payment_exception'], true), 409, 'Pembayaran berhasil. Pembatalan memerlukan penanganan refund oleh admin.');
            if ($payment->status === 'failed') {
                return $payment;
            }
            $actual = $this->gateway->cancelReservationCheckout($payment);

            return $this->apply($payment, $actual);
        });
    }

    public function apply(ReservationPayment $payment, array $actual): ReservationPayment
    {
        $normalized = $this->gateway->normalize($actual, $payment->reference);
        abort_unless($normalized['amount'] === $payment->amount && $normalized['currency'] === 'IDR', 409, 'Nominal pembayaran Midtrans tidak cocok.');

        return DB::transaction(function () use ($payment, $normalized, $actual): ReservationPayment {
            $booking = $this->lockSubject($payment->kind, $payment->booking_id);
            $locked = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($locked->user_id === $booking->user_id && $locked->amount === $normalized['amount'], 409);
            if (in_array($locked->status, ['payment_exception', 'refunded'], true)) {
                return $locked;
            }
            if ($locked->status === 'paid') {
                if ($normalized['status'] === 'failed' || in_array($actual['transaction_status'] ?? '', ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true)) {
                    $locked->update(['status' => 'payment_exception', 'provider_status' => $actual['transaction_status'], 'last_checked_at' => now()]);
                    AuditLog::create(['user_id' => $locked->user_id, 'action' => 'reservation.payment_exception', 'auditable_type' => ReservationPayment::class, 'auditable_id' => $locked->id, 'metadata' => ['kind' => $locked->kind, 'mode' => 'sandbox']]);
                }

                return $locked;
            }
            $next = $locked->status;
            if ($normalized['status'] === 'succeeded') {
                $next = $booking->status === 'cancelled' ? 'payment_exception' : 'paid';
            } elseif ($normalized['status'] === 'failed') {
                if ($booking->status !== 'cancelled') {
                    abort_unless($booking->status === 'reserved_sandbox', 409, 'Pesanan sudah diproses. Hubungi admin.');
                    if ($payment->kind === 'umkm') {
                        UmkmProduct::withTrashed()->whereKey($booking->umkm_product_id)->lockForUpdate()->firstOrFail()->increment('stock', $booking->quantity);
                        $booking->update(['status' => 'cancelled']);
                    } elseif ($payment->kind === 'lodging') {
                        app(LodgingReservationService::class)->cancel($booking->user, $booking->id, true);
                    } else {
                        app(MealReservationService::class)->cancel($booking->user, $booking->id, true);
                    }
                }
                $next = 'failed';
            } elseif ($normalized['status'] === 'pending' && ! in_array($locked->status, ['failed'], true)) {
                $next = 'pending';
            }
            if ($next !== $locked->status) {
                AuditLog::create(['user_id' => $locked->user_id, 'action' => 'reservation.payment_updated', 'auditable_type' => ReservationPayment::class, 'auditable_id' => $locked->id, 'metadata' => ['kind' => $locked->kind, 'status' => $next, 'mode' => 'sandbox']]);
            }
            $locked->update(['status' => $next, 'provider_status' => $actual['transaction_status'] ?? null, 'last_checked_at' => now(), 'paid_at' => in_array($next, ['paid', 'payment_exception'], true) ? now() : $locked->paid_at]);

            return $locked;
        }, 3);
    }

    private function model(string $kind): string
    {
        return match ($kind) {
            'umkm' => UmkmOrder::class,
            'lodging' => LodgingBooking::class,
            'culinary' => MealBooking::class,
            default => abort(404),
        };
    }

    public function lockSubject(string $kind, int $id): Model
    {
        $model = $this->model($kind);
        $candidate = $model::query()->findOrFail($id);
        if ($kind === 'umkm') {
            User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
        } elseif ($kind === 'lodging') {
            RoomType::query()->whereKey($candidate->room_type_id)->lockForUpdate()->firstOrFail();
        } else {
            MealSlot::query()->whereKey($candidate->meal_slot_id)->lockForUpdate()->firstOrFail();
        }

        return $model::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
