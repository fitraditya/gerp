<?php

namespace App\Policies;

class InventoryPolicy
{
    use WarehouseScopePolicy;

    /**
     * View-only through the resource; quantity mutation happens exclusively via
     * InventoryService (receiveStock/transfer/opname), never a raw Filament edit form.
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
