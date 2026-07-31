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
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique(); // uuidv4 or similar
            $table->string('transaction_type'); // sales, expense, remittance, transfer, etc.
            $table->morphs('transactionable'); // polymorphic to different transaction types
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('account_code'); // GL account reference
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['transaction_type', 'warehouse_id']);
            $table->index('account_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
