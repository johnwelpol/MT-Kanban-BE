<?php

namespace Tests\Feature\Http\Controllers\Central;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SlugAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_valid_slug_is_available(): void
    {
        $this->getJson('/api/central/slug-availability?slug=acme-corp', $this->apiHeaders())
            ->assertOk()
            ->assertExactJson(['data' => ['slug' => 'acme-corp', 'available' => true, 'reason' => null]]);
    }

    #[TestWith(['ab'])]
    #[TestWith(['-abc'])]
    #[TestWith(['abc-'])]
    #[TestWith(['ABC'])]
    #[TestWith(['acme_corp'])]
    public function test_malformed_slug_is_invalid(string $slug): void
    {
        $this->getJson("/api/central/slug-availability?slug={$slug}", $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason', 'invalid');
    }

    public function test_reserved_slug_is_reserved(): void
    {
        $this->getJson('/api/central/slug-availability?slug=admin', $this->apiHeaders())
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason', 'reserved');
    }

    public function test_existing_slug_is_taken(): void
    {
        Tenant::factory()->create(['slug' => 'acme']);

        $this->getJson('/api/central/slug-availability?slug=acme', $this->apiHeaders())
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason', 'taken');
    }
}
