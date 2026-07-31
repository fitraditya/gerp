<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    /**
     * Post one balanced double-entry movement: $from loses $amount, $to gains it.
     * Accepts either a cash_accounts.code string or a CashAccount instance (the
     * instance's in-memory state is never trusted — both sides are re-fetched and
     * locked by id inside the transaction). Both rows are locked in a stable
     * (id-sorted) order to avoid deadlocking a concurrent transfer touching the
     * same two accounts in reverse.
     */
    public function post(
        string|CashAccount $from,
        string|CashAccount $to,
        float $amount,
        string $type,
        string $description,
        ?Model $transactionable,
        int $warehouseId,
        ?int $actorId,
        array $metadata = [],
        ?\DateTimeInterface $occurredAt = null
    ): array {
        return DB::transaction(function () use ($from, $to, $amount, $type, $description, $transactionable, $warehouseId, $actorId, $metadata, $occurredAt) {
            $fromCode = $from instanceof CashAccount ? $from->code : $from;
            $toCode = $to instanceof CashAccount ? $to->code : $to;

            $codes = [$fromCode, $toCode];
            sort($codes);

            $accounts = CashAccount::whereIn('code', $codes)->orderBy('id')->lockForUpdate()->get()->keyBy('code');

            $fromAccount = $accounts->get($fromCode);
            $toAccount = $accounts->get($toCode);

            if (!$fromAccount) {
                throw new \RuntimeException("Cash account [{$fromCode}] not found — seed cash_accounts before posting.");
            }
            if (!$toAccount) {
                throw new \RuntimeException("Cash account [{$toCode}] not found — seed cash_accounts before posting.");
            }

            $fromAccount->decrement('balance', $amount);
            $toAccount->increment('balance', $amount);

            $transactionId = (string) Str::uuid();
            $timestamp = $occurredAt ?? now();

            $base = [
                'transaction_id' => $transactionId,
                'transaction_type' => $type,
                'warehouse_id' => $warehouseId,
                'created_by' => $actorId,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if ($transactionable) {
                $base['transactionable_type'] = $transactionable::class;
                $base['transactionable_id'] = $transactionable->getKey();
            }

            $debitRow = Ledger::create($base + [
                'account_code' => $fromCode,
                'debit' => $amount,
                'credit' => 0,
            ]);

            $creditRow = Ledger::create($base + [
                'account_code' => $toCode,
                'debit' => 0,
                'credit' => $amount,
            ]);

            return [
                'from' => $fromAccount->refresh(),
                'to' => $toAccount->refresh(),
                'debit_entry' => $debitRow,
                'credit_entry' => $creditRow,
            ];
        });
    }

    public function balance(string $code): float
    {
        return (float) CashAccount::where('code', $code)->value('balance');
    }

    public static function drawerCode(string $warehouseCode): string
    {
        return "DRAWER-{$warehouseCode}";
    }

    public static function poolCode(string $fundPool): string
    {
        return 'POOL-'.strtoupper($fundPool);
    }
}
