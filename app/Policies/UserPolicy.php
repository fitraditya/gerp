<?php

namespace App\Policies;

class UserPolicy
{
    use RoleGatedPolicy;

    /** User & role management is Admin-only per the RFC Roles & Permission Matrix. */
    protected static function viewRoles(): array
    {
        return [];
    }

    protected static function manageRoles(): array
    {
        return [];
    }
}
