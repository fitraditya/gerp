<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

class OpnameApiTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    public function test_supervisor_can_submit_opname(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $product = Product::factory()->create();
        $supervisor = $this->makeUser('Supervisor', $warehouse->id);
        Sanctum::actingAs($supervisor, ['pos:*']);

        $response = $this->postJson('/api/v1/inventory/opname', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 15,
            'reason_log' => 'Found misplaced sorting box behind shelf cluster B.',
        ]);

        $response->assertStatus(200)->assertJsonPath('status', 'pending');
    }

    public function test_staff_is_forbidden_per_story_3(): void
    {
        // PRD Story 3: "Staff role explicitly blocked."
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $product = Product::factory()->create();
        $staff = $this->makeUser('Staff', $warehouse->id);
        Sanctum::actingAs($staff, ['pos:*']);

        $response = $this->postJson('/api/v1/inventory/opname', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 15,
            'reason_log' => 'Found misplaced sorting box behind shelf cluster B.',
        ]);

        $response->assertStatus(403);
    }

    public function test_reason_log_under_ten_chars_is_rejected(): void
    {
        // PRD AC: "Mandatory justification log required" on short/empty reason.
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $product = Product::factory()->create();
        $supervisor = $this->makeUser('Supervisor', $warehouse->id);
        Sanctum::actingAs($supervisor, ['pos:*']);

        $response = $this->postJson('/api/v1/inventory/opname', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 15,
            'reason_log' => 'too short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('reason_log');
    }
}
