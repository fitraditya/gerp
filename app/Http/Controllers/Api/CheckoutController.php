<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Scopes\WarehouseScope;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkoutService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'discount_id' => ['nullable', 'integer', 'exists:discounts,id'],
            'payment_method' => ['required', 'string', 'in:cash,qris'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $warehouseId = $this->resolveWarehouseId($user, $data['branch_id']);

        try {
            $order = $this->checkoutService->process($data, $warehouseId, $user->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => $order,
            'has_negative_stock_flag' => (bool) $order->has_negative_stock_flag,
        ], 201);
    }

    private function resolveWarehouseId($user, int $branchId): int
    {
        $branch = Branch::withoutGlobalScope(WarehouseScope::class)->findOrFail($branchId);

        if (!$user->hasAnyRole(['Admin', 'Manager']) && $user->warehouse_id !== $branch->warehouse_id) {
            abort(403, 'Token is not scoped to this branch.');
        }

        return $branch->warehouse_id;
    }
}
