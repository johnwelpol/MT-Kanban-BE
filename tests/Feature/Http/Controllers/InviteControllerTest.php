<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class InviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'plain-invite-token';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function invite(array $attributes = []): Invitation
    {
        return Invitation::factory()->for($this->tenant)->create([
            'token_hash' => hash('sha256', self::TOKEN),
            'role' => Role::Admin,
            ...$attributes,
        ]);
    }

    public function test_show_previews_the_invitation(): void
    {
        $invitation = $this->invite(['email' => 'new@example.com']);

        $this->getJson('/api/invites/'.self::TOKEN, $this->apiHeaders($this->tenant))
            ->assertOk()
            ->assertExactJson(['data' => [
                'email' => 'new@example.com',
                'role' => 'admin',
                'workspace' => ['name' => $this->tenant->name, 'slug' => $this->tenant->slug],
                'inviter_name' => $invitation->inviter->name,
                'user_exists' => false,
            ]]);
    }

    public function test_show_returns_404_for_expired_accepted_or_unknown_tokens(): void
    {
        $this->invite(['expires_at' => now()->subMinute()]);
        Invitation::factory()->for($this->tenant)->accepted()->create(['token_hash' => hash('sha256', 'accepted-token')]);

        $this->getJson('/api/invites/'.self::TOKEN, $this->apiHeaders($this->tenant))->assertNotFound();
        $this->getJson('/api/invites/accepted-token', $this->apiHeaders($this->tenant))->assertNotFound();
        $this->getJson('/api/invites/unknown', $this->apiHeaders($this->tenant))->assertNotFound();
    }

    public function test_token_for_another_workspace_returns_404(): void
    {
        $this->invite();

        $this->getJson('/api/invites/'.self::TOKEN, $this->apiHeaders(Tenant::factory()->create()))->assertNotFound();
    }

    public function test_new_user_accepts_and_joins_with_the_invited_role(): void
    {
        $this->invite(['email' => 'new@example.com']);

        $response = $this->postJson('/api/invites/'.self::TOKEN.'/accept', [
            'name' => 'Ada Lovelace',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $this->apiHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.user.email', 'new@example.com')
            ->assertJsonPath('data.user.name', 'Ada Lovelace');

        $user = User::where('email', 'new@example.com')->first();
        $this->assertSame(Role::Admin, $user->membershipIn($this->tenant)->role);
        $this->assertSame($this->tenant->id, PersonalAccessToken::findToken($response->json('data.token'))->tenant_id);
    }

    public function test_new_user_must_provide_name_and_confirmed_password(): void
    {
        $this->invite();

        $this->postJson('/api/invites/'.self::TOKEN.'/accept', ['password' => 'secret-password'], $this->apiHeaders($this->tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'password']);
    }

    public function test_existing_user_accepts_with_their_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->invite(['email' => $user->email, 'role' => Role::Viewer]);

        $this->postJson('/api/invites/'.self::TOKEN.'/accept', ['password' => 'secret-password'], $this->apiHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(Role::Viewer, $user->membershipIn($this->tenant)->role);
    }

    public function test_existing_user_with_wrong_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->invite(['email' => $user->email]);

        $this->postJson('/api/invites/'.self::TOKEN.'/accept', ['password' => 'wrong-password'], $this->apiHeaders($this->tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertFalse($user->belongsToTenant($this->tenant));
    }

    public function test_already_a_member_returns_409(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->memberOf($this->tenant, Role::Viewer, $user);
        $this->invite(['email' => $user->email]);

        $this->postJson('/api/invites/'.self::TOKEN.'/accept', ['password' => 'secret-password'], $this->apiHeaders($this->tenant))
            ->assertStatus(409);
    }

    public function test_accepting_twice_returns_404_the_second_time(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->invite(['email' => $user->email]);
        $uri = '/api/invites/'.self::TOKEN.'/accept';

        $this->postJson($uri, ['password' => 'secret-password'], $this->apiHeaders($this->tenant))->assertOk();
        $this->postJson($uri, ['password' => 'secret-password'], $this->apiHeaders($this->tenant))->assertNotFound();
    }
}
