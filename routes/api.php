<?php

use App\Http\Controllers\Auth\HandoffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CardCommentController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\Central\FindWorkspacesController;
use App\Http\Controllers\Central\RegisterController;
use App\Http\Controllers\Central\SlugAvailabilityController;
use App\Http\Controllers\Internal\ResolveTenantController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MoveCardController;
use App\Http\Controllers\MoveColumnController;
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

        Route::apiResource('boards', BoardController::class);

        // Scoped: a column, card or comment from another board resolves to 404.
        Route::scopeBindings()->prefix('boards/{board}')->group(function () {
            Route::post('columns', [BoardColumnController::class, 'store']);
            Route::patch('columns/{column}', [BoardColumnController::class, 'update']);
            Route::delete('columns/{column}', [BoardColumnController::class, 'destroy']);
            Route::post('columns/{column}/move', MoveColumnController::class);
            Route::post('cards', [CardController::class, 'store']);
            Route::get('cards/{card}', [CardController::class, 'show']);
            Route::patch('cards/{card}', [CardController::class, 'update']);
            Route::delete('cards/{card}', [CardController::class, 'destroy']);
            Route::post('cards/{card}/move', MoveCardController::class);
            Route::post('cards/{card}/comments', [CardCommentController::class, 'store']);
            Route::delete('cards/{card}/comments/{comment}', [CardCommentController::class, 'destroy']);
        });
    });
});
