<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'po_number' => 'PO-'.strtoupper(fake()->unique()->bothify('####-????')),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory(),
            'status' => 'ordered',
            'subtotal' => 0,
            'total' => 0,
            'received_total' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'items' => [],
            'ordered_at' => now(),
        ];
    }
}
