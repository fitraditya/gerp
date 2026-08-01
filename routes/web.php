<?php

use App\Http\Controllers\ExportController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// CSV downloads linked from Filament (FinancialReports/OrderResource/InventoryResource)
// — plain synchronous streamDownload, not Filament's native async export. No 'auth'
// middleware here deliberately: this app has no route named 'login' (Filament's panel
// login isn't registered under that name), so Laravel's default Authenticate middleware
// would throw RouteNotFoundException on redirect instead of redirecting a guest.
// ExportController checks auth()->check() itself and aborts 403 (same pattern
// CheckoutController already uses for authorization failures).
Route::prefix('exports')->name('exports.')->group(function () {
    Route::get('/trial-balance.csv', [ExportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('/profit-and-loss.csv', [ExportController::class, 'profitAndLoss'])->name('profit-and-loss');
    Route::get('/orders.csv', [ExportController::class, 'orders'])->name('orders');
    Route::get('/inventory.csv', [ExportController::class, 'inventory'])->name('inventory');
});

Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->where('locale', 'en|id')->name('locale.switch');
