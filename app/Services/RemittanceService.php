<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Remittance;
use App\Scopes\WarehouseScope;
use Illuminate\Support\Facades\DB;

class RemittanceService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    /**
     * Setoran Kas step 1: source cash account (a specific holder's cash) -> IN_TRANSIT
     * clearing account. Source is explicit, not derived from warehouse, since cash is
     * held per-person in this org.
     */
    public function submit(int $fromWarehouseId, int $toWarehouseId, int $sourceCashAccountId, float $amount, int $submittedBy, ?\DateTimeInterface $occurredAt = null): Remittance
    {
        return DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $sourceCashAccountId, $amount, $submittedBy, $occurredAt) {
            $source = CashAccount::lockForUpdate()->findOrFail($sourceCashAccountId);

            if ($amount > (float) $source->balance) {
                throw new \RuntimeException('Amount exceeds available drawer cash balance.');
            }

            $remittance = Remittance::create([
                'remittance_number' => 'RM-'.uniqid(),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'submitted_by' => $submittedBy,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $this->ledgerService->post(
                from: $source,
                to: 'IN_TRANSIT',
                amount: $amount,
                type: 'remit_out',
                description: "Remittance submit {$remittance->remittance_number}",
                transactionable: $remittance,
                warehouseId: $fromWarehouseId,
                actorId: $submittedBy,
                occurredAt: $occurredAt,
            );

            return $remittance;
        });
    }

    /**
     * Setoran Kas step 2: IN_TRANSIT -> destination cash account (central treasury holder).
     */
    public function verify(Remittance $remittance, int $destinationCashAccountId, int $verifierId, ?\DateTimeInterface $occurredAt = null): Remittance
    {
        return DB::transaction(function () use ($remittance, $destinationCashAccountId, $verifierId, $occurredAt) {
            $remittance = Remittance::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($remittance->id);

            if ($remittance->status !== 'pending') {
                throw new \RuntimeException('Remittance already resolved.');
            }

            $destination = CashAccount::findOrFail($destinationCashAccountId);

            $this->ledgerService->post(
                from: 'IN_TRANSIT',
                to: $destination,
                amount: (float) $remittance->amount,
                type: 'remit_in',
                description: "Remittance verify {$remittance->remittance_number}",
                transactionable: $remittance,
                warehouseId: $remittance->to_warehouse_id,
                actorId: $verifierId,
                occurredAt: $occurredAt,
            );

            $remittance->status = 'verified';
            $remittance->verified_by = $verifierId;
            $remittance->verified_at = now();
            $remittance->completed_at = now();
            $remittance->save();

            event(new \App\Events\RemittanceVerified($remittance));

            return $remittance;
        });
    }
}
