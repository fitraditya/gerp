<?php

namespace App\Policies;

class ExpensePolicy
{
    use WarehouseScopePolicy;

    /** Expense entry / fund-pool routing: Admin + Manager only. */
    protected static function viewRoles(): array
    {
        return ['Manager'];
    }

    protected static function manageRoles(): array
    {
        return ['Manager'];
    }
}
