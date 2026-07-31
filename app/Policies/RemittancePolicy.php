<?php

namespace App\Policies;

use App\Models\Remittance;
use App\Models\User;

class RemittancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
    }

    public function view(User $user, Remittance $model): bool
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

    /** Submit (Setoran Kas step 1) — Admin/Manager/Supervisor. */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
    }

    /** Verify (Setoran Kas step 2) — Admin/Manager only, matches RFC matrix. */
    public function verify(User $user, Remittance $model): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }
}
