<?php

namespace App\Listeners;

use App\Events\RemittanceVerified;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

class LogRemittanceVerified implements ShouldQueueAfterCommit
{
    public function handle(RemittanceVerified $event): void
    {
        $remittance = $event->remittance;

        Log::info('remittance.verified', [
            'action' => 'remittance.verify',
            'entity' => ['type' => 'remittance', 'id' => $remittance->id],
            'actor' => ['user_id' => $remittance->verified_by],
            'amount' => (float) $remittance->amount,
            'from_warehouse_id' => $remittance->from_warehouse_id,
            'to_warehouse_id' => $remittance->to_warehouse_id,
        ]);
    }
}
