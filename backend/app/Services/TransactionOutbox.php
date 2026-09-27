<?php

namespace App\Services;

use App\Mail\TransactionNotice;
use App\Models\NotificationDelivery;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class TransactionOutbox
{
    public function record(Order $order, string $type, string $eventKey, string $detail): NotificationDelivery
    {
        if (! array_key_exists($type, TransactionNotice::TEMPLATES)) {
            throw new \InvalidArgumentException('Unknown transaction notice type.');
        }

        return DB::transaction(function () use ($order, $type, $eventKey, $detail): NotificationDelivery {
            Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            return NotificationDelivery::firstOrCreate([
                'deduplication_key' => hash('sha256', $order->id.'|'.$type.'|'.$eventKey),
            ], [
                'order_id' => $order->id,
                'type' => $type,
                'recipient' => $order->customer_email,
                'snapshot' => ['order_id' => $order->public_id, 'name' => $order->customer_name, 'detail' => $detail],
                'available_at' => now(),
                'delivery_log' => [],
            ]);
        });
    }
}
