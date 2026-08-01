<?php

namespace App\Policies;

class SalesReturnPolicy
{
    use WarehouseScopePolicy;

    /**
     * Same role set as Stock Opname (RFC §2 matrix, Story 3), not POS Checkout — Staff
     * has no Filament resource access at all (AGENTS.md/RBAC.md: "Staff: POS checkout
     * only"), and the only entry point for returns is OrderResource's "Process Return"
     * record action, which Staff can never reach anyway (OrderPolicy already blocks
     * them from viewing Orders). Granting Staff this permission would be a dangling
     * grant with no working channel to use it through.
     */
    protected static function viewRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    protected static function manageRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }
}
