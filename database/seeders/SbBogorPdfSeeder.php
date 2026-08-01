<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashAccount;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Reproduces the "SB Bogor" spreadsheet (Jun–Jul 2026) as real transactions through
 * the actual services (CheckoutService/InventoryService/ExpenseService), so the
 * resulting dashboard tiles can be checked against the source PDF's own totals:
 *
 *   Total Stock Awal   1,572 pcs / Rp31,490,000
 *   Total Stock Akhir  1,193 pcs / Rp23,235,000
 *   Total Sales (Gross) Rp8,255,000
 *   Total Diskon        Rp1,500,000
 *   Total Omzet (Net)   Rp6,755,000
 *   Biaya Pengembangan  Rp3,353,500
 *   Operasional Gerai   Rp50,000
 *   Biaya SDM           Rp750,000 (2 orang: Fadhil, Firman)
 *
 * All of these reconcile exactly given the source data (verified by hand before
 * writing this seeder). "Saldo Kas" (Rp2,516,500 in the PDF) will NOT match exactly —
 * the source Buku Kas excerpt is a partial/truncated OCR (hidden "###" cells, an
 * unexplained Rp1,730,000 opening float) that doesn't fully account for where cash
 * physically ended up. The four real named cash accounts below are seeded with their
 * *reported* ending balances as a snapshot; the branch operating drawer that actually
 * received the replayed sales/expenses is a separate, legitimately-computed balance —
 * see the note at the end of this file.
 */
class SbBogorPdfSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InitialSetupSeeder::class);

        $inventoryService = app(InventoryService::class);
        $checkoutService = app(CheckoutService::class);
        $expenseService = app(ExpenseService::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $central = Warehouse::where('code', 'CENTRAL')->firstOrFail();

        // InitialSetupSeeder's demo "Branch 1" is not part of the SB Bogor dataset and
        // would inflate the gerai count (PDF dashboard: 2 gerai aktif). Nothing in the
        // replay below posts to it, so remove it and its scaffolding.
        Branch::where('code', 'BR1')->delete();
        CashAccount::where('code', LedgerService::drawerCode('BR1'))->delete();
        Warehouse::where('code', 'BR1')->delete();

        // --- Gerai (page 15). The Mitra sheet lists all four partnerships as "Aktif",
        // but the PDF dashboard counts "Total Gerai: 2 (Gerai Aktif)" — only the two
        // operating masjid storefronts (MDIA1/MDIA2, which carry the period's sales,
        // SDM, and operasional). The bazzar rows are event-based partner channels,
        // seeded dormant (is_active = false) until an event activates them. ---
        $geraiData = [
            'MDIA1' => ['type' => 'masjid', 'name' => 'Gerai Masjid Al-Mahdi 1', 'pic' => 'Fadhil', 'address' => 'Masjid Al-Mahdi', 'active' => true],
            'MDIA2' => ['type' => 'masjid', 'name' => 'Gerai Masjid Al-Mahdi 2', 'pic' => 'Rustam', 'address' => 'Masjid Al-Mahdi', 'active' => true],
            'SSYA' => ['type' => 'bazzar', 'name' => 'Sasya Preloved', 'pic' => 'Bu Nia', 'address' => 'Sasya Preloved', 'active' => false],
            'AISY' => ['type' => 'bazzar', 'name' => 'Hafshah Preloved', 'pic' => 'Sisil', 'address' => 'Hafshah Preloved', 'active' => false],
        ];

        $branches = [];
        foreach ($geraiData as $code => $g) {
            $wh = Warehouse::firstOrCreate(
                ['code' => $code],
                ['name' => "{$g['name']} Warehouse", 'type' => 'branch', 'address' => $g['address'], 'is_active' => $g['active']]
            );
            $branches[$code] = Branch::firstOrCreate(
                ['code' => $code],
                ['name' => $g['name'], 'type' => $g['type'], 'pic_name' => $g['pic'], 'warehouse_id' => $wh->id, 'address' => $g['address'], 'is_active' => $g['active']]
            );

            // Every gerai gets its own operating drawer so Expense/Checkout can post to it.
            CashAccount::firstOrCreate(
                ['code' => LedgerService::drawerCode($code)],
                ['name' => "{$g['name']} Drawer", 'account_type' => 'asset', 'branch_id' => $branches[$code]->id, 'balance' => 0, 'is_active' => true]
            );
        }

        // --- Products: 20 price tiers (page 2) ---
        $tiers = [5000, 10000, 15000, 20000, 25000, 30000, 35000, 40000, 45000, 50000, 55000, 60000, 70000, 80000, 90000, 100000, 110000, 120000, 125000, 150000];
        $brand = Brand::firstOrCreate(['name' => 'Sedekah Baju']);

        $products = [];
        foreach ($tiers as $t) {
            $label = 'Rp'.number_format($t, 0, ',', '.').' Tier';
            $products[$t] = Product::firstOrCreate(
                ['sku' => "TIER-{$t}"],
                ['name' => $label, 'brand_id' => $brand->id, 'price' => $t, 'tier' => $label, 'is_active' => true]
            );
        }

        // --- Stock Awal (page 2): two intake batches, both dated 20-Jun-26 ---
        $stockAwal = [
            5000 => [80, 76], 10000 => [182, 85], 15000 => [218, 240], 20000 => [103, 87], 25000 => [107, 93],
            30000 => [78, 38], 35000 => [21, 10], 40000 => [42, 35], 45000 => [0, 0], 50000 => [17, 19],
            55000 => [0, 9], 60000 => [6, 2], 70000 => [2, 10], 80000 => [4, 0], 90000 => [1, 4],
            100000 => [1, 0], 110000 => [0, 0], 120000 => [2, 0], 125000 => [0, 0], 150000 => [0, 0],
        ];
        $openDate = Carbon::parse('2026-06-20 09:00:00');

        foreach ($stockAwal as $tier => [$batchA, $batchB]) {
            if ($batchA > 0) {
                $inventoryService->receiveStock($products[$tier], $central->id, $batchA, $admin->id, $openDate);
            }
            if ($batchB > 0) {
                $inventoryService->receiveStock($products[$tier], $central->id, $batchB, $admin->id, $openDate);
            }
            $total = $batchA + $batchB;
            if ($total > 0) {
                // All initial stock moves to the main storefront (MDIA1) to be sold from.
                $inventoryService->transfer($products[$tier], $central->id, $branches['MDIA1']->warehouse_id, $total, $admin->id, $openDate);
            }
        }

        // --- Cashier tied to MDIA1 ---
        $cashier = User::firstOrCreate(
            ['email' => 'fadhil@sbbogor.test'],
            ['name' => 'Fadhil', 'password' => Hash::make('password'), 'warehouse_id' => $branches['MDIA1']->warehouse_id, 'is_active' => true]
        );
        $cashier->syncRoles(['Staff']);

        // --- Sales (pages 4–5): Total Diskon Rp1,500,000 is a flat "SP Mitra" discount
        // applied to the June bulk sale — 7,260,000 - 1,500,000 = 5,760,000, and with
        // July's 995,000 net, Total Omzet = 6,755,000, matching the PDF exactly. ---
        $discount = Discount::firstOrCreate(
            ['code' => 'SP-MITRA-JUN'],
            ['name' => 'Diskon SP Mitra Juni', 'type' => 'fixed', 'value' => 1500000, 'is_active' => true]
        );

        $salesJun = [5000 => 22, 10000 => 50, 15000 => 40, 20000 => 53, 25000 => 52, 30000 => 55, 35000 => 12, 40000 => 13, 50000 => 10, 60000 => 4, 70000 => 1, 80000 => 1, 90000 => 1, 120000 => 1];
        $salesJul = [5000 => 13, 10000 => 17, 15000 => 20, 20000 => 5, 25000 => 4, 30000 => 1, 40000 => 1, 50000 => 1, 60000 => 1, 80000 => 1];

        $checkoutService->process([
            'idempotency_key' => 'seed-sales-jun-2026',
            'discount_id' => $discount->id,
            'payment_method' => 'cash',
            'items' => collect($salesJun)->map(fn ($qty, $tier) => ['product_id' => $products[$tier]->id, 'quantity' => $qty])->values()->all(),
        ], $branches['MDIA1']->warehouse_id, $cashier->id, Carbon::parse('2026-06-30 17:00:00'));

        $checkoutService->process([
            'idempotency_key' => 'seed-sales-jul-2026',
            'payment_method' => 'cash',
            'items' => collect($salesJul)->map(fn ($qty, $tier) => ['product_id' => $products[$tier]->id, 'quantity' => $qty])->values()->all(),
        ], $branches['MDIA1']->warehouse_id, $cashier->id, Carbon::parse('2026-07-31 17:00:00'));

        // --- Expenses (Buku Kas, pages 7–14): Pengembangan / SDM / Operasional ---
        $mdia1Drawer = CashAccount::where('code', LedgerService::drawerCode('MDIA1'))->firstOrFail();
        $mdia2Warehouse = $branches['MDIA2']->warehouse_id;
        $mdia1Warehouse = $branches['MDIA1']->warehouse_id;

        $expense = function (int $warehouseId, string $category, string $description, float $amount, string $fundPool, ?string $payeeName, Carbon $date) use ($expenseService, $mdia1Drawer, $admin) {
            $expenseService->recordExpense($warehouseId, $mdia1Drawer->id, $category, $description, $amount, $fundPool, 'cash', $payeeName, $admin->id, $date);
        };

        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Hanger 50 Pcs', 55000, 'DEV', null, Carbon::parse('2026-06-30'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Atk HVS & Platik Laminating 1 pack', 201000, 'DEV', null, Carbon::parse('2026-07-01'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Rak Hanger @3 untuk Bazzar 1', 600000, 'DEV', null, Carbon::parse('2026-07-01'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Tenda Bazzar Kecil untuk di Event', 455000, 'DEV', null, Carbon::parse('2026-07-03'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Tenda Bazzar untuk di Gerai Masjid', 845000, 'DEV', null, Carbon::parse('2026-07-03'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Hanger 120 Pcs', 120000, 'DEV', null, Carbon::parse('2026-07-04'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Kalkulator @2', 77500, 'DEV', null, Carbon::parse('2026-07-04'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Keranjang @15', 300000, 'DEV', null, Carbon::parse('2026-07-04'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Plastik Kresek 5 Pack', 100000, 'DEV', null, Carbon::parse('2026-07-04'));
        $expense($mdia1Warehouse, 'Pengembangan', 'Beli Rak Hanger @3 untuk Bazzar 2', 600000, 'DEV', null, Carbon::parse('2026-07-04'));

        $expense($mdia1Warehouse, 'SDM', 'Honor Petugas Gerai 2 Pekan Juni', 400000, 'HR', 'Fadhil', Carbon::parse('2026-06-30'));
        $expense($mdia1Warehouse, 'SDM', 'SDM Gerai MDIA1 Juli pekan 1', 200000, 'HR', 'Fadhil', Carbon::parse('2026-07-10'));
        $expense($mdia1Warehouse, 'SDM', 'SDM Gerai MDIA1 Juli pekan 1', 150000, 'HR', 'Firman', Carbon::parse('2026-07-10'));

        $expense($mdia1Warehouse, 'Operasional', 'Operasional Gerai MDIA1 Juli pekan 1', 25000, 'OPS', null, Carbon::parse('2026-07-10'));
        $expense($mdia2Warehouse, 'Operasional', 'Operasional Gerai MDIA2 Juli pekan 1', 25000, 'OPS', null, Carbon::parse('2026-07-10'));

        // --- Ringkasan Kas (pages 1, 7–8): four real named cash holders, seeded with
        // their reported ending balances. Not derived from the replay above — the
        // source Buku Kas excerpt is partial (truncated "###" cells, an unexplained
        // Rp1,730,000 opening float not tied to any listed transaction) so it can't be
        // reconstructed transaction-by-transaction. These are a snapshot, not a ledger. ---
        $namedAccounts = [
            ['holder' => 'Teh Fitri', 'balance' => 1730000],
            ['holder' => 'Hamdani', 'balance' => 66500],
        ];
        foreach ($namedAccounts as $i => $acc) {
            CashAccount::firstOrCreate(
                ['code' => 'KAS-'.strtoupper(str_replace(' ', '-', $acc['holder']))],
                ['name' => 'Kas SB Bogor', 'holder_name' => $acc['holder'], 'account_type' => 'asset', 'balance' => $acc['balance'], 'counts_as_cash' => true, 'is_active' => true]
            );
        }
        CashAccount::firstOrCreate(
            ['code' => 'REK-CDI'],
            ['name' => 'Rek CDI', 'account_type' => 'asset', 'balance' => 125000, 'counts_as_cash' => true, 'is_active' => true]
        );
        CashAccount::firstOrCreate(
            ['code' => 'QRIS-MASJID'],
            ['name' => 'QRIS Masjid', 'account_type' => 'asset', 'balance' => 595000, 'counts_as_cash' => true, 'is_active' => true]
        );

        $this->command->info('SB Bogor PDF data seeded. MDIA1 drawer (real replay) balance: Rp'.number_format((float) $mdia1Drawer->refresh()->balance, 0, ',', '.'));
        $this->command->info('PDF-reported Saldo Kas (4 named accounts, snapshot): Rp2,516,500 — will differ from live system totals; see class docblock.');
    }
}
