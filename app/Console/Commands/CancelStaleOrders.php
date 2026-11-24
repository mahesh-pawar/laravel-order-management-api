<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelStaleOrders extends Command
{
    protected $signature = 'orders:cancel-stale {--minutes=30 : Cancel pending orders older than this many minutes}';

    protected $description = 'Cancel pending orders older than 30 minutes and restore their stock';

    public function handle(OrderService $orderService): int
    {
        $count = $orderService->cancelStalePending((int) $this->option('minutes'));

        $this->info("Cancelled {$count} stale pending order(s).");

        return self::SUCCESS;
    }
}
