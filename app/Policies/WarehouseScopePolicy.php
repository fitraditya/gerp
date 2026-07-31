<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared warehouse-scoped policy shape: Admin unrestricted, other listed roles limited
 * to their own warehouse, everyone else blocked. Concrete policies override
 * viewRoles()/manageRoles() to match the RFC §2 Roles & Permission Matrix for that
 * resource — methods, not properties: PHP fatals if a trait property and a same-named
 * property in the using class disagree on default value.
 */
trait WarehouseScopePolicy
{
    /**
     * Record-level warehouse check, mirroring WarehouseScope: a user with no
     * warehouse_id is global (RFC §2 matrix — Managers see all branches); a user
     * with one is confined to it.
     */
    protected static function inWarehouseScope(User $user, $model): bool
    {
        return $user->warehouse_id === null || $model->warehouse_id === $user->warehouse_id;
    }

    /** @return string[] non-Admin roles allowed to view */
    protected static function viewRoles(): array
    {
        return ['Manager', 'Supervisor'];
    }

    /** @return string[] non-Admin roles allowed to create/update/delete */
    protected static function manageRoles(): array
    {
        return ['Manager'];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasAnyRole(static::viewRoles());
    }

    public function view(User $user, $model): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
        if (!$user->hasAnyRole(static::viewRoles())) {
            return false;
        }

        return static::inWarehouseScope($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasAnyRole(static::manageRoles());
    }

    public function update(User $user, $model): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
        if (!$user->hasAnyRole(static::manageRoles())) {
            return false;
        }

        return static::inWarehouseScope($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->update($user, $model);
    }

    public function restore(User $user, $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->hasRole('Admin');
    }
}
