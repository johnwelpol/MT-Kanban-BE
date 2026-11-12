<?php

use App\Http\Controllers\Internal\ResolveTenantController;
use App\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->middleware('internal')->group(function () {
    Route::get('tenants/resolve', ResolveTenantController::class);
});

Route::prefix('central')->middleware('internal')->group(function () {
    // B3
});

Route::middleware(['internal', 'tenant', 'tenant.active'])->group(function () {
    // guest tenant routes (B3, B6)
    Route::middleware(['auth:sanctum', 'tenant.member'])->group(function () {
        Route::get('me', MeController::class);
    });
});
