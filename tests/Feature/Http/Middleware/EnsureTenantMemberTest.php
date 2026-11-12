<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTenantMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_token_returns_me_with_role_and_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Viewer);

        $this->getJson('/api/me', $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertExactJson(['data' => [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'workspace' => ['id' => $tenant->id, 'slug' => $tenant->slug, 'name' => $tenant->name],
                'role' => 'viewer',
                'permissions' => ['members.view', 'board.view'],
            ]]);
    }

    public function test_returns_401_when_token_was_issued_for_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        [$user, $tokenForA] = $this->memberOf($tenantA);
        $this->memberOf($tenantB, Role::Member, $user);

        $this->getJson('/api/me', $this->apiHeaders($tenantB, $tokenForA))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_returns_403_when_membership_was_removed(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Member);
        Membership::where('tenant_id', $tenant->id)->where('user_id', $user->id)->delete();

        $this->getJson('/api/me', $this->apiHeaders($tenant, $token))
            ->assertForbidden()
            ->assertExactJson(['message' => 'You are not a member of this workspace.']);
    }

    public function test_returns_401_without_token(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson('/api/me', $this->apiHeaders($tenant))
            ->assertUnauthorized();
    }

    public function test_returns_401_for_token_not_bound_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        [$user] = $this->memberOf($tenant);
        $unboundToken = $user->createToken('web')->plainTextToken;

        $this->getJson('/api/me', $this->apiHeaders($tenant, $unboundToken))
            ->assertUnauthorized();
    }
}
