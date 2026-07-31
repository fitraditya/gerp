<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data correction, not a schema change: fund-pool accounts (POOL-HR/OPS/DEV/DISC) were
 * seeded with account_type 'equity', but they represent spend categories (they only
 * ever receive credit entries from ExpenseService/CheckoutService, never fund anything
 * back out) — that's an expense classification, not equity. See RFC.md Database Model
 * notes ("Chart of Accounts" follow-up, 2026-08-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cash_accounts')
            ->where('code', 'like', 'POOL-%')
            ->where('account_type', 'equity')
            ->update(['account_type' => 'expense']);
    }

    public function down(): void
    {
        DB::table('cash_accounts')
            ->where('code', 'like', 'POOL-%')
            ->where('account_type', 'expense')
            ->update(['account_type' => 'equity']);
    }
};
