<?php

namespace App\Policies;

class PurchaseOrderPolicy
{
    use WarehouseScopePolicy;

    /** Purchasing (Story 1 intake) is Admin/Manager only, same as ProductPolicy/InventoryService::receiveStock. */
    protected static function viewRoles(): array
    {
        return ['Manager'];
    }

    protected static function manageRoles(): array
    {
        return ['Manager'];
    }
}
