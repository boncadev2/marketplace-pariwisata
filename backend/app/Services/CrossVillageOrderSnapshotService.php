<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrossVillageOrderSnapshotService
{
    public function create(Order $order, User $actor, string $version, string $reason, string $ip): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Snapshot hanya tersedia untuk simulasi lokal.');
        abort_unless($actor->platform_role === 'super_admin' && $actor->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($order, $actor, $version, $reason, $ip): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->cross_village_snapshot !== null) {
                abort_unless(hash_equals($locked->cross_village_snapshot['configuration_version'], $version), 409, 'Pesanan sudah memiliki snapshot dari konfigurasi berbeda.');

                return $locked->cross_village_snapshot;
            }
            abort_unless($locked->status === 'paid' && $locked->currency === 'IDR', 422, 'Pesanan harus dibayar dalam IDR.');
            abort_if($locked->subOrders()->exists(), 409, 'Pesanan sudah memiliki sub-order lain.');
            $attempts = $locked->paymentAttempts()->lockForUpdate()->get();
            abort_unless($attempts->isNotEmpty() && $attempts->every(fn ($attempt) => in_array($attempt->provider, ['sandbox', 'midtrans_sandbox'], true))
                && $attempts->contains(fn ($attempt) => $attempt->status === 'succeeded' && $attempt->currency === 'IDR' && (int) $attempt->amount === (int) $locked->total),
                422, 'Pembayaran sandbox yang cocok diperlukan.');
            $items = $locked->items()->lockForUpdate()->get();
            abort_unless($items->count() === 1, 422, 'Simulasi mendukung satu produk paket.');
            $item = $items->first();
            $product = $item->product()->firstOrFail();
            abort_unless($product->type === 'package' && (int) $product->partner_id === (int) $locked->partner_id, 422, 'Pemilik paket tidak sesuai pesanan.');
            $package = $product->tourPackage()->lockForUpdate()->firstOrFail();
            $allocations = app(CrossVillageAllocationService::class);
            $configuration = $allocations->configuration($package);
            abort_unless(hash_equals($configuration['version'], $version), 409, 'Porsi berubah. Muat ulang konfigurasi.');
            $total = $this->rupiah($locked->total);
            $commission = $this->rupiah($item->commission_amount);
            abort_unless($total > 0 && $this->rupiah($item->total) === $total && $commission <= $total, 422, 'Total atau komisi pesanan tidak valid.');

            return $this->persist($locked, $package, $configuration, $actor->id, 'configuration_at_simulation', $reason, $ip);

        }, 3);
    }

    public function captureAtCheckout(Order $order, TourPackage $package, array $configuration, ?User $actor): array
    {
        abort_unless(app()->environment(['local', 'testing']) && DB::transactionLevel() > 0, 503);
        abort_unless($configuration['all_accepted'] && $order->currency === 'IDR' && $order->cross_village_snapshot === null, 422);

        return $this->persist($order, $package, $configuration, $actor?->id, 'configuration_at_checkout', 'Snapshot porsi saat checkout sandbox', '');
    }

    private function persist(Order $locked, TourPackage $package, array $configuration, ?int $actorId, string $basis, string $reason, string $ip): array
    {
        $item = $locked->items()->firstOrFail();
        $product = $package->product;
        $version = $configuration['version'];
        $total = $this->rupiah($locked->total);
        $commission = $this->rupiah($item->commission_amount);
        abort_unless($total > 0 && $commission <= $total, 422, 'Komisi tidak valid.');
        $allocations = app(CrossVillageAllocationService::class);
        $revenues = $allocations->allocate($package, $total - $commission);
        $commissions = $allocations->allocate($package, $commission);
        $rows = [];
        foreach ($revenues as $index => $revenue) {
            $amount = $commissions[$index]['partner_revenue'];
            $rows[] = [...$revenue, 'commission_amount' => $amount, 'subtotal' => $revenue['partner_revenue'] + $amount];
            $locked->subOrders()->create(['partner_id' => $revenue['partner_id'], 'status' => 'simulation_only',
                'subtotal' => $revenue['partner_revenue'] + $amount, 'commission_amount' => $amount, 'partner_revenue' => $revenue['partner_revenue']]);
        }
        $snapshot = ['simulation_only' => true, 'captured_at' => now()->toIso8601String(), 'captured_by' => $actorId,
            'basis' => $basis, 'configuration_version' => $version, 'product_id' => $product->id,
            'order_item_id' => $item->id, 'quantity' => (int) $item->quantity, 'currency' => 'IDR',
            'total' => $total, 'commission_amount' => $commission, 'partner_revenue' => $total - $commission,
            'shares' => $configuration['shares'], 'revision' => $configuration['revision'],
            'agreements' => $configuration['agreements'], 'all_accepted' => $configuration['all_accepted'], 'allocations' => $rows];
        $locked->update(['cross_village_snapshot' => $snapshot]);
        AuditLog::create(['user_id' => $actorId, 'action' => ($basis === 'configuration_at_checkout' ? 'cross_village.checkout_snapshot_created' : 'cross_village.order_snapshot_created'),
            'auditable_type' => Order::class, 'auditable_id' => $locked->id,
            'metadata' => ['reason' => $reason, 'configuration_version' => $version, 'simulation_only' => true],
            'ip_hash' => hash('sha256', $ip)]);

        return $locked->fresh()->cross_village_snapshot;
    }

    private function rupiah(mixed $value): int
    {
        $decimal = (string) $value;
        abort_unless(preg_match('/^\d{1,13}(?:\.0{1,2})?$/', $decimal) === 1, 422, 'Nominal harus rupiah utuh dan tidak negatif.');
        $amount = (int) $decimal;
        abort_if($amount > 1_000_000_000_000, 422, 'Nominal simulasi terlalu besar.');

        return $amount;
    }
}
