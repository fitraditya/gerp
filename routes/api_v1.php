<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OpnameController;
use App\Http\Controllers\Api\RemitController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pos/checkout', [CheckoutController::class, 'store']);
    Route::post('/inventory/opname', [OpnameController::class, 'store']);
    Route::post('/finance/remit', [RemitController::class, 'store']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
});
