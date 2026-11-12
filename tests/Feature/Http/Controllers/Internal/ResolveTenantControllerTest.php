<?php

namespace Tests\Feature\Http\Controllers\Internal;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResolveTenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private const INTERNAL_KEY = 'test-internal-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['frontend.internal_key' => self::INTERNAL_KEY]);
    }

    public function test_returns_workspace_for_active_slug(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/internal/tenants/resolve?slug={$tenant->slug}", ['X-Internal-Key' => self::INTERNAL_KEY])
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ]]);
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/internal/tenants/resolve?slug=no-such-workspace', ['X-Internal-Key' => self::INTERNAL_KEY])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);
    }

    public function test_returns_404_for_suspended_workspace(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->getJson("/api/internal/tenants/resolve?slug={$tenant->slug}", ['X-Internal-Key' => self::INTERNAL_KEY])
            ->assertNotFound();
    }

    public function test_returns_422_when_slug_is_missing(): void
    {
        $this->getJson('/api/internal/tenants/resolve', ['X-Internal-Key' => self::INTERNAL_KEY])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'The slug field is required.']);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('invalidInternalKeyHeaders')]
    public function test_returns_404_without_valid_internal_key(array $headers): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/internal/tenants/resolve?slug={$tenant->slug}", $headers)
            ->assertNotFound()
            ->assertJsonMissingPath('data');
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function invalidInternalKeyHeaders(): array
    {
        return [
            'missing' => [[]],
            'wrong' => [['X-Internal-Key' => 'wrong-key']],
        ];
    }

    public function test_returns_404_when_internal_key_is_not_configured(): void
    {
        config(['frontend.internal_key' => null]);
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/internal/tenants/resolve?slug={$tenant->slug}", ['X-Internal-Key' => ''])
            ->assertNotFound();
    }
}
