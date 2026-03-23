<?php

namespace App\Console\Commands;

use App\Services\OrderCancellationService;
use Illuminate\Console\Command;

class AutoCancelOrders extends Command
{
    protected $signature = 'orders:auto-cancel';

    protected $description = 'Automatically cancel stale pending and accepted orders.';

    public function handle(OrderCancellationService $orderCancellationService): int
    {
        $pendingCancelled = $orderCancellationService->autoCancelStalePendingOrders();
        $acceptedCancelled = $orderCancellationService->autoCancelAcceptedOrdersWithoutMovement();

        $this->info("Auto-cancelled {$pendingCancelled} pending order(s).");
        $this->info("Auto-cancelled {$acceptedCancelled} accepted order(s) without agent movement.");

        return self::SUCCESS;
    }
}
