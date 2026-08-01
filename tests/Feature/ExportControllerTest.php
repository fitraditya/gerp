<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * CSV export routes (ERP-gap follow-up: exportable reports). Authorization mirrors
 * each underlying resource/page exactly — see ExportController's docblock.
 */
class ExportControllerTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    public function test_admin_can_export_trial_balance_csv(): void
    {
        $admin = $this->makeUser('Admin');

        $response = $this->actingAs($admin)->get('/exports/trial-balance.csv');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Account Type,Code,Name,Balance', $content);
        $this->assertStringContainsString('SALES_REVENUE', $content);
        $this->assertStringContainsString('INVENTORY_ASSET', $content);
    }

    public function test_supervisor_cannot_export_trial_balance_csv(): void
    {
        $supervisor = $this->makeUser('Supervisor');

        $response = $this->actingAs($supervisor)->get('/exports/trial-balance.csv');

        $response->assertForbidden();
    }

    public function test_guest_cannot_export_trial_balance_csv(): void
    {
        // No 'auth' middleware on this route group (see routes/web.php) — the
        // controller checks auth itself and aborts 403, same as CheckoutController.
        $response = $this->get('/exports/trial-balance.csv');

        $response->assertForbidden();
    }

    public function test_guest_cannot_export_orders_csv(): void
    {
        $response = $this->get('/exports/orders.csv');

        $response->assertForbidden();
    }

    public function test_admin_can_export_profit_and_loss_csv_for_period(): void
    {
        $manager = $this->makeUser('Manager');
        $warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 5, $manager->id);

        app(CheckoutService::class)->process([
            'idempotency_key' => 'export-pl-key',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $warehouse->id, $manager->id);

        $response = $this->actingAs($manager)->get('/exports/profit-and-loss.csv?'.http_build_query([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Revenue,20000', $content);
        $this->assertStringContainsString('Cost of Goods Sold,8000', $content);
        $this->assertStringContainsString('Gross Profit,12000', $content);
    }

    public function test_manager_orders_export_includes_cost_columns(): void
    {
        $manager = $this->makeUser('Manager');
        $warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 5, $manager->id);
        app(CheckoutService::class)->process([
            'idempotency_key' => 'export-orders-manager',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $warehouse->id, $manager->id);

        $response = $this->actingAs($manager)->get('/exports/orders.csv?'.http_build_query([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('COGS', $content);
        $this->assertStringContainsString('Gross Profit', $content);
    }

    public function test_supervisor_orders_export_omits_cost_columns(): void
    {
        // Cost/margin data is Admin/Manager-only — mirrors CheckoutController::serializeOrder().
        $manager = $this->makeUser('Manager');
        $warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 5, $manager->id);
        app(CheckoutService::class)->process([
            'idempotency_key' => 'export-orders-supervisor',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $warehouse->id, $manager->id);

        $supervisor = $this->makeUser('Supervisor', $warehouse->id);

        $response = $this->actingAs($supervisor)->get('/exports/orders.csv?'.http_build_query([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('COGS', $content);
        $this->assertStringNotContainsString('Gross Profit', $content);
        $this->assertStringContainsString('Negative Stock Flag', $content);
    }

    public function test_staff_cannot_export_orders(): void
    {
        $warehouse = Warehouse::factory()->create();
        $staff = $this->makeUser('Staff', $warehouse->id);

        $response = $this->actingAs($staff)->get('/exports/orders.csv');

        $response->assertForbidden();
    }

    public function test_supervisor_can_export_inventory_csv(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $supervisor = $this->makeUser('Supervisor', $warehouse->id);
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 7, $supervisor->id);

        $response = $this->actingAs($supervisor)->get('/exports/inventory.csv');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString($product->sku, $content);
        $this->assertStringContainsString('7', $content);
    }

    public function test_staff_cannot_export_inventory_csv(): void
    {
        $staff = $this->makeUser('Staff');

        $response = $this->actingAs($staff)->get('/exports/inventory.csv');

        $response->assertForbidden();
    }

    public function test_product_name_starting_with_formula_character_is_neutralized_in_csv(): void
    {
        // OWASP CSV injection: a cell like "=cmd|' /C calc'!A0" opens as a live formula
        // in Excel/Sheets. CsvExportService must prefix a neutralizing apostrophe.
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => '=cmd|\'/C calc\'!A0']);
        $admin = $this->makeUser('Admin');
        app(InventoryService::class)->receiveStock($product, $warehouse->id, 1, $admin->id);

        $response = $this->actingAs($admin)->get('/exports/inventory.csv');

        $content = $response->streamedContent();
        $this->assertStringNotContainsString(',=cmd|', $content);
        $this->assertStringContainsString(",'=cmd|", $content);
    }
}
