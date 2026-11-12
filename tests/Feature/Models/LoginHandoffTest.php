<?php

namespace Tests\Feature\Models;

use App\Models\LoginHandoff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_only_handoffs_expired_more_than_a_day_ago(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $attributes = ['user_id' => $user->id, 'tenant_id' => $tenant->id];

        $stale = LoginHandoff::create([...$attributes, 'code_hash' => hash('sha256', 'stale'), 'expires_at' => now()->subDay()->subMinute()]);
        $recent = LoginHandoff::create([...$attributes, 'code_hash' => hash('sha256', 'recent'), 'expires_at' => now()->subHour()]);

        $this->artisan('model:prune', ['--model' => LoginHandoff::class])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($recent);
    }
}
