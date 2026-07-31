<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    public function test_cashier_can_checkout_at_own_branch(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create(['price' => 10000]);
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 5);

        $cashier = $this->makeUser('Staff', $warehouse->id);
        Sanctum::actingAs($cashier, ['pos:*']);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'idempotency_key' => 'api-key-1',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(201)->assertJsonPath('has_negative_stock_flag', false);
    }

    public function test_cashier_token_scoped_to_own_branch_cannot_checkout_elsewhere(): void
    {
        // RFC §3 Security: "Sanctum tokens are scoped to a single branch; a compromised
        // POS device token cannot act on another branch's inventory/cash."
        $this->seedRolesAndAccounts();
        $ownWarehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $ownWarehouse->id]);
        $otherWarehouse = Warehouse::factory()->create();
        $otherBranch = Branch::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        $product = Product::factory()->create();

        $cashier = $this->makeUser('Staff', $ownWarehouse->id);
        Sanctum::actingAs($cashier, ['pos:*']);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'idempotency_key' => 'api-key-2',
            'branch_id' => $otherBranch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(403);
    }

    public function test_checkout_rejects_invalid_discount_with_422(): void
    {
        // PRD Story 2 AC: unauthorized discount schema blocks checkout.
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create();
        $inactiveDiscount = \App\Models\Discount::factory()->create(['is_active' => false]);

        $cashier = $this->makeUser('Staff', $warehouse->id);
        Sanctum::actingAs($cashier, ['pos:*']);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'idempotency_key' => 'api-key-3',
            'branch_id' => $branch->id,
            'discount_id' => $inactiveDiscount->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_requires_idempotency_key(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $product = Product::factory()->create();
        $cashier = $this->makeUser('Staff', $warehouse->id);
        Sanctum::actingAs($cashier, ['pos:*']);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
    }
}
