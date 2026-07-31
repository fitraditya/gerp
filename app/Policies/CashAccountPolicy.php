<?php

namespace App\Policies;

class CashAccountPolicy
{
    use RoleGatedPolicy;

    /** Read-only per RFC Module Breakdown ("CashAccountResource (read)") — balances are service-managed. */
    protected static function viewRoles(): array
    {
        return ['Manager'];
    }

    protected static function manageRoles(): array
    {
        return [];
    }
}
