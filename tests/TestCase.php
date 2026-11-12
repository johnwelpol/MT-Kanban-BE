<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TenantTokenIssuer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const INTERNAL_KEY = 'test-internal-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['frontend.internal_key' => self::INTERNAL_KEY]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * Headers that the server sends. Do not use Sanctum::actingAs: its transient token has no tenant_id.
     *
     * @return array<string, string>
     */
    protected function apiHeaders(?Tenant $tenant = null, ?string $token = null): array
    {
        return array_filter([
            'X-Internal-Key' => self::INTERNAL_KEY,
            'Accept' => 'application/json',
            'X-Tenant' => $tenant?->getTenantKey(),
            'Authorization' => $token === null ? null : "Bearer {$token}",
        ]);
    }

    /**
     * Create a user with the given role in the tenant and a token issued for that tenant.
     *
     * @return array{0: User, 1: string}
     */
    protected function memberOf(Tenant $tenant, Role $role = Role::Owner, ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role]);

        return [$user, app(TenantTokenIssuer::class)->issue($user, $tenant)];
    }
}
