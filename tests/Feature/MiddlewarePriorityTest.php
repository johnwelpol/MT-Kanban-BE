<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantMember;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Tests\TestCase;

/**
 * Guards cross-tenant leaks: route model binding must resolve inside the tenant scope.
 */
class MiddlewarePriorityTest extends TestCase
{
    public function test_tenancy_and_membership_run_before_route_model_binding(): void
    {
        $this->assertLessThan($this->priorityOf(SubstituteBindings::class), $this->priorityOf(InitializeTenancyByRequestData::class));
        $this->assertLessThan($this->priorityOf(SubstituteBindings::class), $this->priorityOf(EnsureTenantMember::class));
    }

    public function test_tenancy_runs_before_authentication(): void
    {
        $this->assertLessThan($this->priorityOf(AuthenticatesRequests::class), $this->priorityOf(InitializeTenancyByRequestData::class));
    }

    private function priorityOf(string $middleware): int
    {
        $position = array_search($middleware, app(Router::class)->middlewarePriority, true);

        $this->assertIsInt($position, "{$middleware} is missing from the middleware priority list.");

        return $position;
    }
}
