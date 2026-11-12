<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    private const FAILED_MESSAGE = 'These credentials do not match our records.';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_member_with_correct_password_gets_tenant_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->tenant->users()->attach($user, ['role' => Role::Member]);

        $response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->apiHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame($this->tenant->id, PersonalAccessToken::findToken($response->json('data.token'))->tenant_id);
    }

    public function test_non_member_with_correct_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->apiHeaders($this->tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::FAILED_MESSAGE]);
    }

    public function test_wrong_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->tenant->users()->attach($user, ['role' => Role::Member]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong-password'], $this->apiHeaders($this->tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::FAILED_MESSAGE]);
    }

    public function test_unknown_email_returns_422(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever'], $this->apiHeaders($this->tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::FAILED_MESSAGE]);
    }

    public function test_sixth_attempt_in_a_minute_returns_429(): void
    {
        $credentials = ['email' => 'ada@example.com', 'password' => 'wrong-password'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', $credentials, $this->apiHeaders($this->tenant))->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', $credentials, $this->apiHeaders($this->tenant))->assertTooManyRequests();
    }
}
