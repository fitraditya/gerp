<?php

namespace App\Policies;

use App\Models\User;

/**
 * For global (non-warehouse-owned) master data: Product, Brand, Warehouse, Branch,
 * Discount, CashAccount. Role-gated only — there is no per-row ownership to check.
 *
 * Concrete policies override viewRoles()/manageRoles(), not properties: a trait
 * property and a same-named property in the using class is a fatal PHP conflict
 * the moment their default values differ, so the role lists live behind methods.
 */
trait RoleGatedPolicy
{
    /** @return string[] non-Admin roles allowed to view */
    protected static function viewRoles(): array
    {
        return ['Manager'];
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
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasAnyRole(static::manageRoles());
    }

    public function update(User $user, $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasRole('Admin');
    }

    public function restore(User $user, $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->hasRole('Admin');
    }
}
