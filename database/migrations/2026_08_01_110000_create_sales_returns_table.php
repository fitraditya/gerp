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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Copied from orders.warehouse_id at process time — own column (not a join)
            // so WarehouseScope can filter this table the same way every other
            // warehouse-owned model does.
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->json('items'); // [{product_id, quantity, unit_price, refund_subtotal, unit_cost, cost_subtotal}]
            $table->decimal('refund_amount', 12, 2);
            // Sum of cost_subtotal across returned lines — lets DashboardService/
            // LedgerReportService net returns out of period revenue/COGS/gross-profit
            // without re-deriving it from the items JSON on every query.
            $table->decimal('cogs_reversal', 12, 2)->default(0);
            $table->string('refund_method')->nullable(); // cash, qris — defaults to the order's payment_method
            $table->string('status')->default('completed');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('order_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
