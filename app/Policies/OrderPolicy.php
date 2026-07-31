<?php

namespace App\Policies;

class OrderPolicy
{
    use WarehouseScopePolicy;

    /**
     * Read-only order log per RFC Module Breakdown — orders are only ever created
     * via CheckoutService (Filament/API), never through a raw create/edit form here.
     */
    protected static function viewRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    protected static function manageRoles(): array
    {
        return [];
    }
}
