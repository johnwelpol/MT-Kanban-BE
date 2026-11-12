<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Tenancy\TenantQueryGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope is a dev dependency: only registered locally, and only when installed.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
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

        $this->configureRateLimiting();

        if ($this->app->environment(['local', 'testing'])) {
            TenantQueryGuard::register();
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('slug-check', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('find-workspaces', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            tenant()?->getTenantKey().'|'.Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));
        RateLimiter::for('handoff', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
