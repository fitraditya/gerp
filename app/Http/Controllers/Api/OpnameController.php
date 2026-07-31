<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Scopes\WarehouseScope;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['Admin', 'Manager', 'Supervisor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'physical_qty' => ['required', 'integer'],
            'reason_log' => ['required', 'string', 'min:10'],
        ], [
            'reason_log.min' => 'Mandatory justification log required.',
        ]);

        $branch = Branch::withoutGlobalScope(WarehouseScope::class)->findOrFail($data['branch_id']);

        if (!$user->hasAnyRole(['Admin', 'Manager']) && $user->warehouse_id !== $branch->warehouse_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $audit = $this->inventoryService->submitOpname(
            $data['product_id'],
            $branch->warehouse_id,
            $data['physical_qty'],
            $data['reason_log'],
            $user->id,
        );

        return response()->json([
            'variance' => $audit->actual_qty - $audit->expected_qty,
            'status' => $audit->status,
            'audit_id' => $audit->id,
        ]);
    }
}
