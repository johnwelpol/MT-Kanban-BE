<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_renames_the_workspace_and_the_resolve_cache_is_cleared(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        [, $token] = $this->memberOf($tenant, Role::Admin);
        Cache::put('tenant-resolve:acme', $tenant, 60);

        $this->patchJson('/api/workspace', ['name' => 'Acme Corp'], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $tenant->id, 'slug' => 'acme', 'name' => 'Acme Corp']]);

        $this->assertSame('Acme Corp', Tenant::find($tenant->id)->name);
        $this->assertFalse(Cache::has('tenant-resolve:acme'));
    }

    public function test_member_cannot_rename_the_workspace(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);

        $this->patchJson('/api/workspace', ['name' => 'Hijacked'], $this->apiHeaders($tenant, $token))
            ->assertForbidden();
    }

    public function test_name_must_be_2_to_60_characters(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Owner);

        $this->patchJson('/api/workspace', ['name' => 'A'], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
