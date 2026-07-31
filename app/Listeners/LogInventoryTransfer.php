<?php

namespace App\Listeners;

use App\Events\InventoryTransferred;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

class LogInventoryTransfer implements ShouldQueueAfterCommit
{
    public function handle(InventoryTransferred $event): void
    {
        $transfer = $event->transfer;

        Log::info('inventory.transferred', [
            'action' => 'inventory.transfer',
            'entity' => ['type' => 'inventory_transfer', 'id' => $transfer->id],
            'actor' => ['user_id' => $transfer->created_by],
            'product_id' => $transfer->product_id,
            'from_warehouse_id' => $transfer->from_warehouse_id,
            'to_warehouse_id' => $transfer->to_warehouse_id,
            'quantity' => $transfer->quantity,
        ]);
    }
}
