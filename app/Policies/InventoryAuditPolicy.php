<?php

namespace App\Policies;

use App\Models\InventoryAudit;
use App\Models\User;

class InventoryAuditPolicy
{
    use WarehouseScopePolicy;

    protected static function viewRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    /** submit (create) per Story 3; Staff blocked */
    protected static function manageRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    /** Approving a pending count is a separate ability from submitting one. */
    public function verify(User $user, InventoryAudit $model): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        // null warehouse_id = global Manager (RFC §2 matrix), mirroring WarehouseScope.
        return $user->hasRole('Manager')
            && ($user->warehouse_id === null || $model->warehouse_id === $user->warehouse_id);
    }
}
