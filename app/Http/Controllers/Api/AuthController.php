<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $branch = \App\Models\Branch::withoutGlobalScope(\App\Scopes\WarehouseScope::class)->findOrFail($data['branch_id']);

        // POS device tokens are scoped to a single branch: a compromised device token
        // can only ever act within its assigned warehouse, never another branch's.
        if (!$user->hasRole('Admin') && $user->warehouse_id !== $branch->warehouse_id) {
            throw ValidationException::withMessages(['branch_id' => 'User is not assigned to this branch.']);
        }

        $token = $user->createToken("pos-{$branch->code}", ['pos:*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'warehouse_id' => $branch->warehouse_id,
            ],
        ]);
    }
}
