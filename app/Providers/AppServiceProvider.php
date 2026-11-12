<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $permission = Permission::tryFrom($ability);

            // Non-permission abilities fall through to policies.
            if ($permission === null) {
                return null;
            }

            return $user->currentRole()?->can($permission) ?? false;
        });
    }
}
