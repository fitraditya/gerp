# RBAC & Authorization Setup

## Overview

The ERP system implements role-based access control (RBAC) using **Spatie Laravel Permission** and **Global Scopes** to ensure users can only access data belonging to their assigned warehouse.

## Roles

The system defines four roles:

- **Admin**: Full access to all warehouses and features
- **Manager**: Global across all warehouses per the RFC §2 matrix (create Manager users with `warehouse_id = null`); a Manager given a `warehouse_id` is confined to that warehouse
- **Supervisor**: Can submit stock opname and cash remittance for assigned warehouse
- **Staff**: Can perform checkout only for assigned warehouse

**Scoping convention (single source of truth):** `warehouse_id = null` on a user means *global*; a set `warehouse_id` means *confined to it*. `WarehouseScope` (query filtering) and every policy's record-level check (`WarehouseScopePolicy::inWarehouseScope()`, `RemittancePolicy::view()`, `InventoryAuditPolicy::verify()`, `InventoryTransferPolicy::view()`) all apply this same rule — if you add a policy or scope, mirror it.

## How It Works

### 1. Global Warehouse Scope

**File**: `app/Scopes/WarehouseScope.php`

A global scope automatically filters queries for non-Admin users:

```php
// A Supervisor queries orders:
Order::all(); // Returns only orders from their warehouse
```

**Models with WarehouseScope applied:**
- `Order`
- `Inventory`
- `Expense`
- `InventoryAudit`
- `Ledger`
- `PurchaseOrder`
- `SalesReturn` (role set matches Stock Opname/`InventoryAudit` — Manager/Supervisor, Staff blocked; Staff has no Filament resource access to reach the "Process Return" action anyway)

**`Remittance` is the exception** — its table has `from_warehouse_id`/`to_warehouse_id`, not a single `warehouse_id` column, so the generic scope's `where('warehouse_id', ...)` fatals for non-Admin users. Row visibility for Remittance is instead enforced by `RemittancePolicy` + `RemittanceResource::getEloquentQuery()` (filters `from_warehouse_id OR to_warehouse_id = user->warehouse_id`). Apply the same pattern to any future model that doesn't own a single `warehouse_id` (e.g. `InventoryTransfer`, gated by `InventoryTransferPolicy` the same way).

### 2. Policy-Based Authorization

**Files**: `app/Policies/WarehouseScopePolicy.php` (warehouse-owned rows: Inventory, InventoryAudit, Expense, PurchaseOrder) and `app/Policies/RoleGatedPolicy.php` (global master data: Product, Brand, Warehouse, Branch, Discount, CashAccount, Supplier).

Both are traits that expose `viewRoles()`/`manageRoles()` as **overridable static methods, not properties** — a trait property and a same-named property in the using class is a fatal PHP error the moment their default values differ, so role lists must be methods:

```php
use App\Policies\WarehouseScopePolicy;

class YourModelPolicy
{
    use WarehouseScopePolicy;

    protected static function viewRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    protected static function manageRoles(): array
    {
        return ['Manager'];
    }
}
```

Register the policy in `app/Providers/AuthServiceProvider.php`'s `$policies` array (also registered in `bootstrap/providers.php`).

Models with a two-sided warehouse relationship (`InventoryTransfer`, `Remittance`) get a bespoke policy instead of the trait, since `view()` has to check two FK columns, not one.

### 3. Dashboard Widget Visibility

**File**: `app/Filament/Widgets/*.php`

Widgets define who can view them:

```php
public static function canView(): bool
{
    $user = Auth::user();
    return $user && $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
}
```

(`User` has no `getPrimaryRoleAttribute()` accessor — use Spatie's `hasRole()`/`hasAnyRole()`.)

- **InventoryHealthWidget**: Admin, Manager, Supervisor
- **CashPositionWidget**: Admin, Manager only
- **SalesTrendWidget**: Admin, Manager, Supervisor
- **ErpDashboard COGS/Gross Profit tiles**: Admin, Manager only. `ErpDashboard` itself is visible to Admin/Manager/Supervisor, but the margin row (`resources/views/filament/pages/erp-dashboard.blade.php`) is wrapped in `@if (auth()->user()->hasAnyRole(['Admin', 'Manager']))` — cost data (`Product.cost_price`) is only editable/visible to Admin/Manager on `ProductResource` (`RoleGatedPolicy` default), so derived margin numbers stay behind the same boundary rather than leaking to a branch Supervisor.
- **FinancialReports page** (Trial Balance + P&L): Admin, Manager only (`canAccess()`), same reasoning as the dashboard margin tiles — this page shows `COGS_EXPENSE`/`INVENTORY_ASSET` account detail directly.
- **POS checkout API response**: `CheckoutController::serializeOrder()` strips `cogs_total`/`gross_profit`/`unit_cost`/`cost_subtotal` from the JSON response unless the caller (`$request->user()`) is Admin/Manager — a Staff cashier's own checkout response must not leak cost/margin data just because they placed the order. Mirror this if you add any other endpoint that serializes `Order` directly.
- **CSV exports** (`/exports/*`, `ExportController`): not a new permission concept — each route reuses the same policy/page-gate its Filament counterpart already uses (`FinancialReports::canAccess()` for trial-balance/P&L, `Order`/`Inventory` policies for the other two), and none of them bypass `WarehouseScope`. The orders export also mirrors the checkout-response cost-data gate above.

---

## Seeding Initial Data

Initial roles and an Admin user are created in `database/seeders/InitialSetupSeeder.php`:

```bash
php artisan db:seed --class=InitialSetupSeeder
```

Default Admin credentials:
- Email: `admin@example.com` (or `ERP_ADMIN_EMAIL` env var)
- Password: `password` (or `ERP_ADMIN_PASSWORD` env var)

---

## Adding A New User

Assign roles to users:

```php
$user = User::create([
    'name' => 'John Supervisor',
    'email' => 'john@example.com',
    'password' => bcrypt('secret'),
    'warehouse_id' => 2, // Warehouse ID
    'is_active' => true,
]);

$user->assignRole('Supervisor');
```

---

## Creating Filament Resources with RBAC

When scaffolding Filament Resources, ensure they respect authorization:

```php
php artisan make:filament-resource OrderResource --generate
```

Update the resource to enforce policies:

```php
protected static ?string $model = Order::class;

public static function canViewAny(User $user): bool
{
    return $user->hasRole(['Admin', 'Manager', 'Supervisor']);
}

public static function canCreate(User $user): bool
{
    return $user->hasRole(['Admin', 'Manager']);
}
```

---

## Environment Variables

Add to `.env`:

```env
ERP_ADMIN_EMAIL=admin@example.com
ERP_ADMIN_PASSWORD=your_secure_password
```

---

## Testing RBAC

Login as different roles and verify:

1. **Admin** sees all warehouses
2. **Manager** sees only their warehouse
3. **Supervisor** can view (read-only) their warehouse
4. **Staff** has limited access to POS checkout only

---

## References

- [Spatie Laravel Permission Docs](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Laravel Authorization Docs](https://laravel.com/docs/11.x/authorization)
