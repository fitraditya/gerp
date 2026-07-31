<?php

namespace Tests\Feature\Policies;

use App\Models\Expense;
use App\Models\InventoryAudit;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * RFC §2 Roles & Permission Matrix, record-level enforcement. Regression coverage
 * for the null-warehouse_id-means-global fix: a Manager with warehouse_id = null
 * must pass every record check regardless of which warehouse owns the record; a
 * Manager/Supervisor with a set warehouse_id stays confined to it.
 */
class PolicyMatrixTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    public function test_global_manager_can_view_and_update_expense_in_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $expense = Expense::withoutGlobalScope(WarehouseScope::class)->create([
            'reference_number' => 'EXP-TEST-1',
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->makeUser('Admin')->id,
            'category' => 'DEV',
            'description' => 'Test',
            'amount' => 1000,
            'fund_pool' => 'DEV',
            'status' => 'recorded',
        ]);

        $globalManager = $this->makeUser('Manager', null);

        $this->assertTrue($globalManager->can('view', $expense));
        $this->assertTrue($globalManager->can('update', $expense));
    }

    public function test_confined_manager_cannot_touch_other_warehouse_expense(): void
    {
        $ownWarehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();
        $expense = Expense::withoutGlobalScope(WarehouseScope::class)->create([
            'reference_number' => 'EXP-TEST-2',
            'warehouse_id' => $otherWarehouse->id,
            'created_by' => $this->makeUser('Admin')->id,
            'category' => 'DEV',
            'description' => 'Test',
            'amount' => 1000,
            'fund_pool' => 'DEV',
            'status' => 'recorded',
        ]);

        $confinedManager = $this->makeUser('Manager', $ownWarehouse->id);

        $this->assertFalse($confinedManager->can('view', $expense));
        $this->assertFalse($confinedManager->can('update', $expense));
    }

    public function test_supervisor_is_blocked_from_expense_module_per_story_4(): void
    {
        $warehouse = Warehouse::factory()->create();
        $expense = Expense::withoutGlobalScope(WarehouseScope::class)->create([
            'reference_number' => 'EXP-TEST-3',
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->makeUser('Admin')->id,
            'category' => 'DEV',
            'description' => 'Test',
            'amount' => 1000,
            'fund_pool' => 'DEV',
            'status' => 'recorded',
        ]);

        $supervisor = $this->makeUser('Supervisor', $warehouse->id);

        $this->assertFalse($supervisor->can('view', $expense));
        $this->assertFalse($supervisor->can('create', Expense::class));
    }

    public function test_global_manager_can_verify_inventory_audit_in_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $audit = InventoryAudit::withoutGlobalScope(WarehouseScope::class)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->makeUser('Supervisor', $warehouse->id)->id,
            'expected_qty' => 0,
            'actual_qty' => 10,
            'notes' => 'Recount after event',
            'status' => 'pending',
        ]);

        $globalManager = $this->makeUser('Manager', null);
        $this->assertTrue($globalManager->can('verify', $audit));

        $confinedManager = $this->makeUser('Manager', Warehouse::factory()->create()->id);
        $this->assertFalse($confinedManager->can('verify', $audit));
    }

    public function test_staff_is_blocked_from_submitting_opname_per_story_3(): void
    {
        $warehouse = Warehouse::factory()->create();
        $staff = $this->makeUser('Staff', $warehouse->id);

        $this->assertFalse($staff->can('create', InventoryAudit::class));
    }

    public function test_global_manager_can_view_remittance_touching_any_warehouse(): void
    {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();
        $remittance = Remittance::create([
            'remittance_number' => 'RM-TEST-1',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'submitted_by' => $this->makeUser('Supervisor', $fromWarehouse->id)->id,
            'amount' => 50000,
            'status' => 'pending',
        ]);

        $globalManager = $this->makeUser('Manager', null);
        $this->assertTrue($globalManager->can('view', $remittance));

        $confinedManager = $this->makeUser('Manager', Warehouse::factory()->create()->id);
        $this->assertFalse($confinedManager->can('view', $remittance));

        // Verify (step 2) is Admin/Manager only per RFC matrix — Supervisor cannot verify.
        $supervisor = $this->makeUser('Supervisor', $fromWarehouse->id);
        $this->assertFalse($supervisor->can('verify', $remittance));
        $this->assertTrue($globalManager->can('verify', $remittance));
    }

    public function test_global_manager_can_view_inventory_transfer_touching_any_warehouse(): void
    {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $transfer = InventoryTransfer::create([
            'transfer_number' => 'TF-TEST-1',
            'product_id' => $product->id,
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'quantity' => 10,
            'created_by' => $this->makeUser('Admin')->id,
            'status' => 'completed',
        ]);

        $globalManager = $this->makeUser('Manager', null);
        $this->assertTrue($globalManager->can('view', $transfer));

        $confinedManager = $this->makeUser('Manager', Warehouse::factory()->create()->id);
        $this->assertFalse($confinedManager->can('view', $transfer));
    }
}
