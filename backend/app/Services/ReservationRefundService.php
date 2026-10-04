<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Payments\MidtransSandboxGateway;
use App\Services\Refund\MidtransRefundAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReservationRefundService
{
    public function __construct(private ReservationPaymentService $payments, private MidtransRefundAdapter $provider) {}

    public function request(ReservationPayment $payment, User $user, string $reason): ReservationRefund
    {
        return DB::transaction(function () use ($payment, $user, $reason): ReservationRefund {
            $booking = $this->payments->lockSubject($payment->kind, $payment->booking_id);
            $locked = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($locked->user_id === $user->id, 404);
            abort_unless($locked->status === 'paid' && $booking->status === 'reserved_sandbox' && ! $booking->getAttribute('checked_in_at') && ! $booking->getAttribute('completed_at'), 409, 'Refund hanya dapat diajukan sebelum produk atau layanan diproses.');
            abort_if($payment->kind === 'lodging' && $booking->check_in->toDateString() <= now('Asia/Jakarta')->toDateString(), 409, 'Tanggal menginap sudah tiba. Hubungi admin untuk penanganan manual.');
            abort_if($payment->kind === 'culinary' && $booking->time_slot->lte(now()), 409, 'Jadwal layanan sudah lewat. Hubungi admin.');
            abort_if(ReservationRefund::where('reservation_payment_id', $locked->id)->exists(), 409, 'Refund sudah pernah diajukan.');
            $refund = ReservationRefund::create(['reservation_payment_id' => $locked->id, 'user_id' => $user->id, 'reason' => $reason, 'refundable_amount' => $locked->amount, 'status' => 'requested']);
            AuditLog::create(['user_id' => $user->id, 'action' => 'reservation.refund_requested', 'auditable_type' => ReservationRefund::class, 'auditable_id' => $refund->id]);

            return $refund;
        }, 3);
    }

    public function decide(ReservationRefund $refund, User $admin, string $action, string $notes): ReservationRefund
    {
        DB::transaction(function () use ($refund, $admin, $action, $notes): void {
            $payment = $refund->payment;
            $booking = $this->payments->lockSubject($payment->kind, $payment->booking_id);
            $locked = ReservationRefund::query()->lockForUpdate()->findOrFail($refund->id);
            abort_unless($locked->status === 'requested', 409, 'Refund sudah diputuskan.');
            abort_unless($payment->fresh()->status === 'paid' && $booking->status === 'reserved_sandbox', 409, 'Status pembayaran atau pesanan berubah. Hubungi operasional.');
            $locked->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'decided_by' => $admin->id, 'decision_notes' => $notes]);
            AuditLog::create(['user_id' => $admin->id, 'action' => 'reservation.refund_'.$action, 'auditable_type' => ReservationRefund::class, 'auditable_id' => $refund->id]);
        }, 3);
        if ($action === 'approve') {
            $this->process($refund->fresh());
        }

        return $refund->fresh();
    }

    public function process(ReservationRefund $refund): ReservationRefund
    {
        return Cache::lock('reservation-refund:'.$refund->id, 60)->block(5, function () use ($refund): ReservationRefund {
            $refund->refresh();
            if ($refund->status === 'succeeded') {
                return $refund;
            }
            abort_unless(in_array($refund->status, ['approved', 'processing'], true), 409, 'Refund belum disetujui.');
            $refund->update(['status' => 'processing']);
            $payment = $refund->payment;
            abort_unless($payment->provider === app(MidtransSandboxGateway::class)->provider(), 409, 'Pembayaran berasal dari lingkungan Midtrans berbeda.');
            $result = $this->provider->processRecord($refund, $payment->reference, $payment->amount, 'wisata-reservation-refund-'.$refund->id);
            if (! ($result['confirmed'] ?? false)) {
                $refund->update(['status' => ($result['pending'] ?? false) ? 'processing' : 'failed', 'failure_reason' => $result['failure_reason'] ?? 'Refund belum dikonfirmasi.']);

                return $refund->fresh();
            }

            return DB::transaction(function () use ($refund, $payment, $result): ReservationRefund {
                $booking = $this->payments->lockSubject($payment->kind, $payment->booking_id);
                $lockedPayment = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
                $locked = ReservationRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if ($locked->status === 'succeeded') {
                    return $locked;
                }
                abort_unless($locked->status === 'processing' && $booking->status === 'reserved_sandbox', 409, 'Status pesanan berubah saat refund. Pemeriksaan manual diperlukan.');
                if ($payment->kind === 'umkm') {
                    UmkmProduct::withTrashed()->whereKey($booking->umkm_product_id)->lockForUpdate()->firstOrFail()->increment('stock', $booking->quantity);
                    $booking->update(['status' => 'cancelled']);
                } elseif ($payment->kind === 'lodging') {
                    app(LodgingReservationService::class)->cancel($booking->user, $booking->id, true);
                } else {
                    app(MealReservationService::class)->cancel($booking->user, $booking->id, true);
                }
                $lockedPayment->update(['status' => 'refunded']);
                $locked->update(['status' => 'succeeded', 'provider_reference' => $result['provider_reference'], 'processed_at' => now(), 'failure_reason' => null]);
                AuditLog::create(['user_id' => $locked->user_id, 'action' => 'reservation.refund_verified', 'auditable_type' => ReservationRefund::class, 'auditable_id' => $locked->id, 'metadata' => ['amount' => $locked->refundable_amount, 'provider_reference' => $result['provider_reference']]]);

                return $locked;
            }, 3);
        });
    }
}
