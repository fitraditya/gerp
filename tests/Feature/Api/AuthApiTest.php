<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    public function test_login_issues_token_for_matching_branch(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $user = $this->makeUser('Staff', $warehouse->id);
        $user->forceFill(['password' => Hash::make('secret123')])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user', 'branch']);
    }

    public function test_login_rejects_user_not_assigned_to_branch(): void
    {
        $this->seedRolesAndAccounts();
        $ownWarehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();
        $otherBranch = Branch::factory()->create(['warehouse_id' => $otherWarehouse->id]);
        $user = $this->makeUser('Staff', $ownWarehouse->id);
        $user->forceFill(['password' => Hash::make('secret123')])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'branch_id' => $otherBranch->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->seedRolesAndAccounts();
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $user = $this->makeUser('Staff', $warehouse->id);
        $user->forceFill(['password' => Hash::make('secret123')])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(422);
    }
}
