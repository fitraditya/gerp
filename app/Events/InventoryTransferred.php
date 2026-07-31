<?php

namespace App\Events;

use App\Models\InventoryTransfer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(public InventoryTransfer $transfer)
    {
    }
}
