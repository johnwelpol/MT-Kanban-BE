<?php

namespace Tests\Feature\Models;

use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_excludes_accepted_and_expired_invitations(): void
    {
        $tenant = Tenant::factory()->create();
        $pending = Invitation::factory()->for($tenant)->create();
        Invitation::factory()->for($tenant)->accepted()->create();
        Invitation::factory()->for($tenant)->expired()->create();

        $this->assertEquals([$pending->id], Invitation::pending()->pluck('id')->all());
    }

    public function test_prunes_only_invitations_expired_more_than_30_days_ago(): void
    {
        $tenant = Tenant::factory()->create();
        $stale = Invitation::factory()->for($tenant)->create(['expires_at' => now()->subDays(31)]);
        $recent = Invitation::factory()->for($tenant)->create(['expires_at' => now()->subDays(29)]);

        $this->artisan('model:prune', ['--model' => Invitation::class])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($recent);
    }
}
