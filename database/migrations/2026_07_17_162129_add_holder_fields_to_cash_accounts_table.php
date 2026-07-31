<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            // Real cash is held per-person, optionally scoped to a branch (a holder can
            // also be branch-independent, e.g. a courier collecting across gerai).
            $table->string('holder_name')->nullable()->after('name');
            $table->foreignId('branch_id')->nullable()->after('holder_name')->constrained('branches')->nullOnDelete();
            // Fund pools and the revenue account are running totals, not spendable cash —
            // exclude them from "Saldo Kas" (total cash on hand) rollups.
            $table->boolean('counts_as_cash')->default(true)->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['holder_name', 'branch_id', 'counts_as_cash']);
        });
    }
};
