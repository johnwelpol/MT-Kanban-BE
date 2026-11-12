<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_deletes_the_token_so_it_stops_working(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant);

        $this->postJson('/api/auth/logout', [], $this->apiHeaders($tenant, $token))->assertNoContent();

        $this->assertNull(PersonalAccessToken::findToken($token));

        // Forget the user resolved during the logout request so /me re-authenticates.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/me', $this->apiHeaders($tenant, $token))->assertUnauthorized();
    }
}
