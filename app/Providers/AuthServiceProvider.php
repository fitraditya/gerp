<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashAccount;
use App\Models\Discount;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryTransfer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\BranchPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CashAccountPolicy;
use App\Policies\DiscountPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InventoryAuditPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\InventoryTransferPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RemittancePolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Warehouse::class => WarehousePolicy::class,
        Branch::class => BranchPolicy::class,
        Brand::class => BrandPolicy::class,
        User::class => UserPolicy::class,
        Discount::class => DiscountPolicy::class,
        CashAccount::class => CashAccountPolicy::class,
        Inventory::class => InventoryPolicy::class,
        InventoryTransfer::class => InventoryTransferPolicy::class,
        InventoryAudit::class => InventoryAuditPolicy::class,
        Expense::class => ExpensePolicy::class,
        Remittance::class => RemittancePolicy::class,
        Order::class => OrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Policies can be registered here if needed
    }
}
