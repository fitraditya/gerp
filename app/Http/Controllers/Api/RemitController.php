<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use App\Services\RemittanceService;
use Illuminate\Http\Request;

class RemitController extends Controller
{
    public function __construct(private RemittanceService $remittanceService)
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
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branch = Branch::withoutGlobalScope(WarehouseScope::class)->findOrFail($data['branch_id']);

        if (!$user->hasAnyRole(['Admin', 'Manager']) && $user->warehouse_id !== $branch->warehouse_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $centralWarehouse = Warehouse::withoutGlobalScope(WarehouseScope::class)->where('type', 'central')->firstOrFail();

        $sourceCashAccountId = $data['cash_account_id'] ?? null;
        if (!$sourceCashAccountId) {
            $branchAccounts = CashAccount::where('branch_id', $branch->id)->where('counts_as_cash', true)->get();
            if ($branchAccounts->count() !== 1) {
                return response()->json(['message' => 'Specify cash_account_id — branch has more than one (or no) cash holder account.'], 422);
            }
            $sourceCashAccountId = $branchAccounts->first()->id;
        }

        try {
            $remittance = $this->remittanceService->submit(
                $branch->warehouse_id,
                $centralWarehouse->id,
                $sourceCashAccountId,
                (float) $data['amount'],
                $user->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json($remittance, 201);
    }
}
