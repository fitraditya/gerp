# AGENTS.md — GM ERP Instructions

> **Single source of truth for all AI agents (Claude, Cursor, Copilot).**  
> Edit only this file. All other agent config files route here.

## Before you start

1. **Read the requirement source:** Open `docs/PRD.md` and find the user story ID or acceptance criterion for this task. Read `docs/RFC.md` §2 for technical design context.
2. **Verify the baseline:** Run `php artisan migrate --step` to ensure database is up-to-date, then run `php artisan test` to confirm all tests green.
3. **Stop if red:** Do not proceed if tests fail or migrations error. Report the failure and let a human debug first.

## Definition of done

- [ ] Code compiles: `php artisan tinker` starts without errors
- [ ] All tests pass: `php artisan test` exits with code 0
- [ ] Models use current patterns: New models follow [Naming Conventions](#naming-conventions)
- [ ] Migrations are timestamped: Migration filename is `YYYY_MM_DD_HHMMSS_<action>.php`
- [ ] RBAC scoped correctly: Non-Admin entities include `WarehouseScope` global, policies added to `app/Policies/`
- [ ] Docs are in sync: Check `docs/Documentation.md` table for which docs to update; update them before committing
- [ ] No silent failures: Database locks use `lockForUpdate()` in all `CheckoutService` and `InventoryService` methods
- [ ] Scope respected: Do not add Admin-only features to Staff/Supervisor endpoints without explicit permission gating

## Project overview

**Gerai Masjid ERP (GM ERP)** — Laravel 11 + Filament 3 real-time inventory, checkout, and general ledger system for multi-branch donation retail. Supports resilient POS checkout with negative stock tolerance, branch-scoped dashboards, RBAC (Admin/Manager/Supervisor/Staff), and double-entry ledger accounting. Single MySQL database, Laravel Sanctum for API tokens, Spatie permissions for role-based access control, Redis for caching and locks.

## Safe commands

```bash
# Setup & teardown
composer install                          # Install dependencies
php artisan migrate --step                # Run pending migrations
php artisan db:seed --class=InitialSetupSeeder  # Seed roles and admin user
php artisan cache:clear && php artisan config:clear

# Development
php artisan serve                         # Start dev server on http://localhost:8000
php artisan tinker                        # Interactive PHP REPL

# Testing & Quality
php artisan test                          # Run PHPUnit test suite
php artisan test --filter=CheckoutTest   # Run specific test class
php artisan test --coverage              # Generate coverage report

# Database & Seeding
php artisan make:migration <name>         # Create new migration
php artisan make:model -m <name>          # Create model + migration
php artisan migrate:rollback              # Rollback last batch

# Filament Admin
php artisan make:filament-resource <Model> --generate # Scaffold resource
php artisan filament:install --panels    # Initialize Filament panel

# Debugging
php artisan route:list                    # List all routes
php artisan config:show database          # Show database config
```

## Naming conventions

| Concept | Pattern | Example | Location |
|---|---|---|---|
| **Model** | `PascalCase.php` | `Product.php`, `InventoryAudit.php` | `app/Models/` |
| **Migration** | `YYYY_MM_DD_HHMMSS_<action>.php` | `2026_07_17_142433_create_products_table.php` | `database/migrations/` |
| **Service** | `PascalCase + Service.php` | `CheckoutService.php`, `InventoryService.php` | `app/Services/` |
| **Policy** | `PascalCase + Policy.php` | `WarehouseScopePolicy.php` | `app/Policies/` |
| **Global Scope** | `PascalCase + Scope.php` | `WarehouseScope.php` | `app/Scopes/` |
| **Filament Resource** | `PascalCase + Resource.php` | `ProductResource.php`, `OrderResource.php` | `app/Filament/Resources/` |
| **Filament Widget** | `PascalCase + Widget.php` | `InventoryHealthWidget.php`, `SalesTrendWidget.php` | `app/Filament/Widgets/` |
| **Controller** | `PascalCase + Controller.php` | `CheckoutController.php` (if used) | `app/Http/Controllers/` |
| **Database Column** | `snake_case` | `warehouse_id`, `created_by`, `quantity_reserved` | Migrations |
| **Eloquent Relationship** | `camelCase()` | `warehouse()`, `createdBy()`, `scopeActive()` | Model methods |
| **Test Class** | `PascalCase + Test.php` | `CheckoutServiceTest.php`, `InventoryTest.php` | `tests/Unit/` or `tests/Feature/` |
| **Guard (Auth)** | `snake_case` | `web` (Filament), `sanctum` (API) | `config/auth.php` |

## Database transactions & row-level locking

**Where used:** `InventoryService`, `CheckoutService`

**Pattern:** All writes to inventory and orders use `DB::transaction()` with `lockForUpdate()` to prevent race conditions during concurrent checkout bursts.

```php
// Real example from CheckoutService::process()
return DB::transaction(function () use ($payload, $warehouseId, $cashierId) {
    $order = Order::create([...]);
    
    foreach ($order->items as $item) {
        $inventory = Inventory::where('product_id', $item['product_id'])
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()  // ← Row-level lock
            ->first();
        
        if (!$inventory) {
            $inventory = Inventory::create([...]);
            $inventory->lockForUpdate();
        }
        
        $inventory->quantity -= $item['quantity'];  // Allow negative
        $inventory->save();
    }
    
    return $order;
});
```

**Key constraint:** Every inventory or order mutation must:
1. Start with `DB::transaction()`
2. Lock all affected rows with `.lockForUpdate()`
3. Negative stock is allowed at checkout; flag it for audit later
4. Commit or abort atomically

## Global scopes & RBAC

**Where applied:** Orders, Inventory, Expenses, InventoryAudit, Remittance, Ledger

**Behavior:** Non-Admin users automatically see only their `warehouse_id`. Admins see all warehouses. Scopes are registered in model's `booted()` method:

```php
protected static function booted()
{
    static::addGlobalScope(new WarehouseScope());
}
```

**Policy enforcement:**
- **Admin:** No restrictions
- **Manager:** Can create/edit/delete resources in their warehouse
- **Supervisor:** Can view and submit (e.g., stock opname), cannot delete
- **Staff:** POS checkout only; no resource access

**Update docs when:** Adding a new scope, changing permission logic → update `docs/RBAC.md`

## Event sourcing & asynchronous handlers (future)

**Status:** Event infrastructure is in place (Laravel Events/Listeners framework available), but handlers are not yet implemented.

**When to add:** When Supervisor alerts, dashboard cache busting, or audit log writes need to happen asynchronously without blocking checkout latency.

**Pattern:**
```php
// Dispatch event after transaction commits
event(new InventoryTransferred($order, $inventory));

// Listen in app/Listeners/CacheInventoryHealth.php
class CacheInventoryHealth implements ShouldQueue {
    public function handle(InventoryTransferred $event) { /* ... */ }
}
```

## Architecture

**Layers:**

| Layer | Folder | Responsibility | Hard Constraint |
|---|---|---|---|
| **Models** | `app/Models/` | Eloquent entities, relationships, scopes, casts | No business logic; scopes only |
| **Services** | `app/Services/` | Core business logic, transactions, locking, validation | Must wrap all inventory/order mutations |
| **Filament Resources** | `app/Filament/Resources/` | Admin UI forms, tables, actions | Use policies for gating; call Services, not Models directly |
| **Filament Widgets** | `app/Filament/Widgets/` | Dashboard cards, charts | Read-only; filter by user warehouse |
| **Policies** | `app/Policies/` | Authorization rules | Register in `app/Providers/AuthServiceProvider.php` |
| **Scopes** | `app/Scopes/` | Automatic query filtering | Applied to Models in `booted()` |
| **Migrations** | `database/migrations/` | Schema definitions | Timestamp always; use foreign keys |
| **Seeders** | `database/seeders/` | Test data or initial setup | Run via `php artisan db:seed` |

**Documentation hub:** [docs/RFC.md](docs/RFC.md) — §2 Architecture diagram and Module Breakdown table.

**Spokes (per-feature docs):**
- [docs/RBAC.md](docs/RBAC.md) — Role definitions, permission matrix, scoping rules
- [docs/Documentation.md](docs/Documentation.md) — What docs to update when code changes

## Doc updates are part of the task

When your code changes models, services, migrations, or permissions, update the relevant doc immediately:

| What changed | Action | File |
|---|---|---|
| Added new Filament Resource | Add row to Module Breakdown table; add permission scope | `docs/RFC.md` §2; `docs/RBAC.md` |
| Added new Service | Describe responsibility; add to architecture diagram | `docs/RFC.md` §2 Architecture |
| Changed permission logic | Update Role & Permission Matrix | `docs/RFC.md` §2; `docs/RBAC.md` |
| New model with warehouse scope | Confirm WarehouseScope is applied; document in scoping rules | `docs/RBAC.md` |
| New naming pattern | Update Naming Conventions table | `AGENTS.md` Naming Conventions |
| Schema change (soft-deletes, timestamps, new field types) | Document in RFC if it affects acceptance criteria | `docs/RFC.md` or relevant story |

**Rule:** No code commit without corresponding doc update. Run `docs/Documentation.md` checklist before pushing.

## How to add a new feature

### Example: Add a new Filament Resource (e.g., `CashAccountResource`)

1. **Create model with relationships** → `php artisan make:model CashAccount -m`
   - File: `app/Models/CashAccount.php`
   - Add fillable, casts, relationships
   - Do NOT add business logic

2. **Create migration → `database/migrations/YYYY_MM_DD_HHMMSS_create_cash_accounts_table.php`
   - Define columns, indexes, foreign keys
   - Use `$table->softDeletes()` if auditable
   - Use `$table->timestamps()` for created_at/updated_at

3. **Create Service (if needed)** → `app/Services/CashAccountService.php`
   - Wrap all mutations in `DB::transaction()`
   - Use `.lockForUpdate()` on related tables
   - Separate read logic from writes

4. **Create Filament Resource** → `php artisan make:filament-resource CashAccount --generate`
   - File: `app/Filament/Resources/CashAccountResource.php`
   - Add form fields, table columns, filters
   - Gate with `canViewAny()`, `canCreate()` policies

5. **Seed initial data (if applicable)** → Update `database/seeders/InitialSetupSeeder.php`
   - Add role-based test data
   - Ensure all warehouse_id values match existing warehouses

6. **Write tests** → `tests/Feature/CashAccountResourceTest.php`
   - Test authorization (Admin sees all, Manager sees own warehouse)
   - Test CRUD via Filament → Service → Database
   - Test WarehouseScope filtering

7. **Update docs** → `docs/RFC.md` + `docs/RBAC.md`
   - Add to Module Breakdown table
   - Add permission scope rules
   - Update user story acceptance criteria if applicable

8. **Verify** → Run `php artisan test` && `composer lint` (if Pint configured)

## Testing and CI

**Test runner:** PHPUnit (Laravel's default)

| When | Required test | Location | Isolation |
|---|---|---|---|
| Add/change Service | Service test (unit) | `tests/Unit/Services/` | Mock DB calls; test logic in isolation |
| Add Model scope or relationship | Model test (unit) | `tests/Unit/Models/` | In-memory SQLite or test DB with migrations |
| Add Filament Resource | Feature test | `tests/Feature/CashAccountResourceTest.php` | Full Filament flow; real test DB |
| Add API endpoint (future) | Feature test | `tests/Feature/Api/` | Real test DB; test auth + endpoint contract |
| Change permission logic | Policy test (unit) | `tests/Unit/Policies/` | Mock User with roles; test policy outcomes |
| Add warehouse scope | Model test (feature) | `tests/Feature/Models/` | Test DB; seed users with different warehouses; verify scope filtering |

**Test patterns:**

```php
// Unit test: CheckoutService locks rows and handles negative stock
public function test_checkout_allows_negative_stock()
{
    $order = CheckoutService::process($payload, $warehouseId, $cashierId);
    $this->assertLessThan(0, $order->items()->first()->inventory->quantity);
}

// Feature test: Manager sees only own warehouse
public function test_manager_sees_only_own_warehouse()
{
    $manager = User::factory()->create(['warehouse_id' => 1])->assignRole('Manager');
    $this->actingAs($manager);
    $orders = Order::all();
    $this->assertTrue($orders->every(fn ($o) => $o->warehouse_id === 1));
}

// Policy test: Supervisor cannot delete
public function test_supervisor_cannot_delete_order()
{
    $supervisor = User::factory()->create(['warehouse_id' => 1])->assignRole('Supervisor');
    $order = Order::factory()->create(['warehouse_id' => 1]);
    $this->assertFalse($supervisor->can('delete', $order));
}
```

**No-mock rule for Services:** Do NOT mock database calls in Service tests. Services must be integration tests; they orchestrate real transactions and locks. Mock only external APIs (SMS, payment gateways) that are out of scope.

**Always-in-force rules:**
- Coverage floor: 70% for new code (run `php artisan test --coverage`)
- Async setup: Use SQLite in-memory for unit tests; use Docker MySQL for feature tests (CI only)
- No silent failures: Every database mutation must be inside `DB::transaction()` + `.lockForUpdate()`

## Things to watch out for

- **WarehouseScope gotcha:** Models apply WarehouseScope automatically. If a query needs to bypass scope (e.g., Admin audit), use `withoutGlobalScope(WarehouseScope::class)`. Always comment why.

- **Soft-delete + global scope interaction:** Soft-deleted records still apply WarehouseScope. Use `withTrashed()` or `onlyTrashed()` carefully; test both Admin and non-Admin visibility.

- **Negative stock is intentional:** POS allows checkout to go negative. Do NOT add validation that prevents it. Negative stock is flagged for Supervisor audit in a future event handler.

- **Migration timestamps must be unique:** Laravel enforces batch uniqueness. If two migrations have identical timestamps, the second will fail silently on key duplicate checks. Always generate fresh timestamps: `date +'%Y_%m_%d_%H%M%S'`.

- **Fillable + Mass Assignment:** Models use `#[Fillable([...])]` attribute. When adding a new column that should be writable via API/form, add it to Fillable, NOT just to the table. Otherwise mass assignment will silently ignore it.

- **Role checks are case-sensitive:** `$user->hasRole('Admin')` returns false if role is stored as `admin`. Seed roles as PascalCase: `Role::firstOrCreate(['name' => 'Admin'])`.

- **Filament Resource name determines route:** `CashAccountResource` → `/dashboard/cash-accounts`. Rename the class carefully; it changes routes and breaks links.

- **Sanctum guards need middleware:** API endpoints require `.middleware('auth:sanctum')` on routes. Filament already uses session auth. Do NOT mix guards on a single route.

- **Policies require registration:** New Policy classes must be registered in `app/Providers/AuthServiceProvider.php` or use Laravel's auto-discovery (9.24+). If policy doesn't fire, check registration first.

- **Test database cleanup:** PHPUnit uses test DB transactions. If a test modifies a model's static state or cache, clean it up in `tearDown()`. Global scopes can cause test pollution if not reset between tests.

---

**Questions?** Refer to `docs/RFC.md` for design decisions, `docs/RBAC.md` for permission rules, or `docs/PRD.md` for user stories.
