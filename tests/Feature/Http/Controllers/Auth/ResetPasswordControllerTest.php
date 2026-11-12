<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_changes_the_password_and_revokes_every_token(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Member);
        $this->memberOf(Tenant::factory()->create(), Role::Member, $user);
        $resetToken = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ], $this->apiHeaders($tenant))
            ->assertOk()
            ->assertExactJson(['data' => ['reset' => true]]);

        $this->assertTrue(Hash::check('new-secret-password', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->getJson('/api/me', $this->apiHeaders($tenant, $token))->assertUnauthorized();
    }

    public function test_invalid_token_returns_422_on_email(): void
    {
        $tenant = Tenant::factory()->create();
        [$user] = $this->memberOf($tenant, Role::Member);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'wrong-token',
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ], $this->apiHeaders($tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This password reset token is invalid.']);
    }
}
