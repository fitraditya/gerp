<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LedgerService;
use Spatie\Permission\Models\Role;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['Admin', 'Manager', 'Supervisor', 'Staff'];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        $central = Warehouse::firstOrCreate(
            ['code' => 'CENTRAL'],
            ['name' => 'Central Warehouse', 'type' => 'central', 'address' => 'Head Office']
        );

        $branchWarehouse = Warehouse::firstOrCreate(
            ['code' => 'BR1'],
            ['name' => 'Branch 1 Warehouse', 'type' => 'branch', 'address' => 'Branch 1']
        );

        Branch::firstOrCreate(
            ['code' => 'BR1'],
            ['name' => 'Branch 1', 'warehouse_id' => $branchWarehouse->id, 'address' => 'Branch 1']
        );

        $adminEmail = env('ERP_ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ERP_ADMIN_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => Hash::make($adminPassword),
                'warehouse_id' => $central->id,
                'is_active' => true,
            ]
        );

        $admin->assignRole('Admin');

        $this->seedCashAccounts($central, $branchWarehouse);

        $this->command->info("Seeded roles, warehouses, cash accounts, and Admin user: {$adminEmail}");
    }

    private function seedCashAccounts(Warehouse ...$warehouses): void
    {
        // Revenue and fund-pool accounts are accounting constructs / running totals —
        // not real money sitting anywhere — so they must never count toward "Saldo Kas"
        // (total cash on hand). IN_TRANSIT and QRIS_CLEARING ARE real money, just not
        // physically in a drawer, so they stay counted.
        $globalAccounts = [
            ['code' => 'SALES_REVENUE', 'name' => 'Sales Revenue', 'account_type' => 'revenue', 'counts_as_cash' => false],
            ['code' => 'QRIS_CLEARING', 'name' => 'QRIS Clearing', 'account_type' => 'asset', 'counts_as_cash' => true],
            ['code' => 'IN_TRANSIT', 'name' => 'Remittance In-Transit', 'account_type' => 'asset', 'counts_as_cash' => true],
            // Spend categories, not owner capital — 'expense', not 'equity' (see
            // 2026_08_01_090000_reclassify_fund_pool_accounts_as_expense migration).
            ['code' => LedgerService::poolCode('HR'), 'name' => 'Fund Pool: HR', 'account_type' => 'expense', 'counts_as_cash' => false],
            ['code' => LedgerService::poolCode('OPS'), 'name' => 'Fund Pool: Operations', 'account_type' => 'expense', 'counts_as_cash' => false],
            ['code' => LedgerService::poolCode('DEV'), 'name' => 'Fund Pool: Development', 'account_type' => 'expense', 'counts_as_cash' => false],
            ['code' => LedgerService::poolCode('DISC'), 'name' => 'Fund Pool: Discretionary', 'account_type' => 'expense', 'counts_as_cash' => false],
            // Inventory-on-hand valued at cost (InventoryService::receiveStock's optional
            // funding-source posting) and its offsetting expense at sale time
            // (CheckoutService's COGS posting) — see RFC.md Ledger Mechanics.
            ['code' => 'INVENTORY_ASSET', 'name' => 'Inventory Asset', 'account_type' => 'asset', 'counts_as_cash' => false],
            ['code' => 'COGS_EXPENSE', 'name' => 'Cost of Goods Sold', 'account_type' => 'expense', 'counts_as_cash' => false],
            // Purchasing (PurchaseOrderService): a "source" account like SALES_REVENUE —
            // its balance goes more negative as goods are received on credit (abs value =
            // amount owed) and back toward zero as payments post. Not meant to be read
            // directly for "amount owed" reporting; use SUM(purchase_orders.balance_due)
            // instead (same reasoning DashboardService already applies to SALES_REVENUE:
            // compute from the source rows, not this account's running total).
            ['code' => 'ACCOUNTS_PAYABLE', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'counts_as_cash' => false],
        ];

        foreach ($globalAccounts as $account) {
            CashAccount::firstOrCreate(['code' => $account['code']], $account + ['balance' => 0, 'is_active' => true]);
        }

        foreach ($warehouses as $warehouse) {
            $code = LedgerService::drawerCode($warehouse->code);
            CashAccount::firstOrCreate(
                ['code' => $code],
                ['name' => "{$warehouse->name} Drawer", 'account_type' => 'asset', 'balance' => 0, 'is_active' => true]
            );
        }
    }
}
