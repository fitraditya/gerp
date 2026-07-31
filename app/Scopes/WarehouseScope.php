<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class WarehouseScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Filters queries to a user's warehouse unless user is an Admin.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Check every configured guard, not just the default ('web'), so this scope
        // still applies correctly once API requests authenticate via 'sanctum' —
        // Auth::user() alone only resolves the default guard and would silently
        // return an unscoped query for API callers otherwise.
        $user = Auth::user();
        foreach (array_keys(config('auth.guards', [])) as $guard) {
            if ($user) {
                break;
            }
            $user = Auth::guard($guard)->user();
        }

        if (!$user || $user->hasRole('Admin')) {
            return;
        }

        if ($user->warehouse_id) {
            $builder->where('warehouse_id', $user->warehouse_id);
        }
    }
}
