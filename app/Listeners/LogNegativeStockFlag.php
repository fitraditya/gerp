<?php

namespace App\Listeners;

use App\Events\NegativeStockFlag;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

class LogNegativeStockFlag implements ShouldQueueAfterCommit
{
    public function handle(NegativeStockFlag $event): void
    {
        $order = $event->order;

        Log::warning('inventory.negative_stock_flag', [
            'action' => 'checkout.negative_stock',
            'entity' => ['type' => 'order', 'id' => $order->id],
            'actor' => ['user_id' => $order->cashier_id],
            'warehouse_id' => $order->warehouse_id,
            'order_number' => $order->order_number,
        ]);

        // Supervisor notification channel is out of scope for MVP (PRD: deferred to Phase 2);
        // the queued log entry + Order.has_negative_stock_flag are the audit surface for now.
    }
}
