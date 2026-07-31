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
        Schema::table('orders', function (Blueprint $table) {
            // Snapshotted at checkout time (product.cost_price can change later) so
            // historical orders keep reporting the margin that actually applied.
            $table->decimal('cogs_total', 12, 2)->default(0)->after('total');
            $table->decimal('gross_profit', 12, 2)->default(0)->after('cogs_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cogs_total', 'gross_profit']);
        });
    }
};
