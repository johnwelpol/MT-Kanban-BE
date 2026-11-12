<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/**
 * Single-database tenancy: no database is created, migrated or deleted per tenant.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, array<int, class-string>>
     */
    public function events(): array
    {
        return [
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        InitializeTenancyByRequestData::$header = 'X-Tenant';
        InitializeTenancyByRequestData::$queryParameter = null; // never identify by ?tenant=
        InitializeTenancyByRequestData::$onFail = fn ($exception, $request, $next) => response()->json(['message' => 'Workspace not found.'], 404);
        QueueTenancyBootstrapper::$forceRefresh = true; // re-initialize tenancy for every job
    }
}
