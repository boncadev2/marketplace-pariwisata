<?php

namespace App\Console\Commands;

use App\Services\ExpireOrderPayments;
use App\Services\InventoryReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:release-expired-holds')]
#[Description('Release expired inventory holds')]
class ReleaseExpiredInventoryHolds extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InventoryReservationService $inventoryReservationService): int
    {
        $released = $inventoryReservationService->releaseExpired();
        $this->info("Released {$released} expired inventory hold(s).");
        $expiredOrders = app(ExpireOrderPayments::class)->run();
        $this->info("Expired {$expiredOrders} payment window(s).");

        return self::SUCCESS;
    }
}
