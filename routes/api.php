<?php

use App\Http\Controllers\Auth\HandoffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Central\FindWorkspacesController;
use App\Http\Controllers\Central\RegisterController;
use App\Http\Controllers\Central\SlugAvailabilityController;
use App\Http\Controllers\Internal\ResolveTenantController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\Workspace\PublicWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->middleware('internal')->group(function () {
    Route::get('tenants/resolve', ResolveTenantController::class);
});

Route::prefix('central')->middleware('internal')->group(function () {
    Route::get('slug-availability', SlugAvailabilityController::class)->middleware('throttle:slug-check');
    Route::post('register', RegisterController::class)->middleware('throttle:register');
    Route::post('find-workspaces', FindWorkspacesController::class)->middleware('throttle:find-workspaces');
});

Route::middleware(['internal', 'tenant', 'tenant.active'])->group(function () {
    Route::get('workspace/public', PublicWorkspaceController::class);
    Route::post('auth/login', LoginController::class)->middleware('throttle:login');
    Route::post('auth/handoff', HandoffController::class)->middleware('throttle:handoff');

    Route::middleware(['auth:sanctum', 'tenant.member'])->group(function () {
        Route::post('auth/logout', LogoutController::class);
        Route::get('me', MeController::class);
    });
});
