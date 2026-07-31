<?php

namespace App\Policies;

use App\Models\InventoryTransfer;
use App\Models\User;

class InventoryTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
    }

    public function view(User $user, InventoryTransfer $model): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
        if (!$user->hasAnyRole(['Manager', 'Supervisor'])) {
            return false;
        }

        // null warehouse_id = global user (RFC §2 matrix), mirroring WarehouseScope.
        return $user->warehouse_id === null
            || in_array($user->warehouse_id, [$model->from_warehouse_id, $model->to_warehouse_id], true);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }
}
