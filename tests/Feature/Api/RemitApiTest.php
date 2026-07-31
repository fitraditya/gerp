<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

class RemitApiTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    public function test_supervisor_can_submit_remittance(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['branch_id' => $branch->id, 'counts_as_cash' => true, 'balance' => 500000]);
        $supervisor = $this->makeUser('Supervisor', $warehouse->id);
        Sanctum::actingAs($supervisor, ['pos:*']);

        $response = $this->postJson('/api/v1/finance/remit', [
            'branch_id' => $branch->id,
            'amount' => 200000,
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'pending');
    }

    public function test_amount_exceeding_balance_returns_400(): void
    {
        // PRD AC: "Amount exceeds available drawer cash balance" -> 400.
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['branch_id' => $branch->id, 'counts_as_cash' => true, 'balance' => 100000]);
        $supervisor = $this->makeUser('Supervisor', $warehouse->id);
        Sanctum::actingAs($supervisor, ['pos:*']);

        $response = $this->postJson('/api/v1/finance/remit', [
            'branch_id' => $branch->id,
            'amount' => 150000,
        ]);

        $response->assertStatus(400);
    }

    public function test_staff_is_forbidden_from_remittance_per_matrix(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        CashAccount::factory()->create(['branch_id' => $branch->id, 'counts_as_cash' => true, 'balance' => 500000]);
        $staff = $this->makeUser('Staff', $warehouse->id);
        Sanctum::actingAs($staff, ['pos:*']);

        $response = $this->postJson('/api/v1/finance/remit', [
            'branch_id' => $branch->id,
            'amount' => 100000,
        ]);

        $response->assertStatus(403);
    }
}
