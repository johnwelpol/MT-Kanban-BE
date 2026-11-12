<?php

namespace Tests\Feature\Http\Controllers\Workspace;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_current_workspace_without_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson('/api/workspace/public', $this->apiHeaders($tenant))
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $tenant->id, 'slug' => $tenant->slug, 'name' => $tenant->name]]);
    }

    public function test_suspended_workspace_returns_404(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->getJson('/api/workspace/public', $this->apiHeaders($tenant))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);
    }

    public function test_unknown_tenant_header_returns_404(): void
    {
        $this->getJson('/api/workspace/public', [...$this->apiHeaders(), 'X-Tenant' => '00000000-0000-0000-0000-000000000000'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);
    }
}
