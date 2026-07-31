<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\InitialSetupSeeder;

/**
 * Shared setup for feature tests: roles (Admin/Manager/Supervisor/Staff) and the
 * global cash accounts (SALES_REVENUE, QRIS_CLEARING, IN_TRANSIT, POOL-*) that
 * LedgerService::post() requires to exist before any sale/expense/remittance posts.
 * Reuses InitialSetupSeeder so tests stay honest about what production actually seeds.
 */
trait SeedsErp
{
    protected function seedRolesAndAccounts(): void
    {
        $this->seed(InitialSetupSeeder::class);
    }

    protected function makeUser(string $role, ?int $warehouseId = null): User
    {
        $user = User::factory()->create([
            'warehouse_id' => $warehouseId,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function centralWarehouse(): Warehouse
    {
        return Warehouse::where('code', 'CENTRAL')->firstOrFail();
    }
}
