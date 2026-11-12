<?php

namespace Tests\Feature\Http\Controllers\Central;

use App\Enums\Role;
use App\Models\LoginHandoff;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'workspace_name' => 'Acme',
            'workspace_slug' => 'acme',
            ...$overrides,
        ];
    }

    public function test_new_email_creates_user_workspace_owner_membership_and_handoff(): void
    {
        $response = $this->postJson('/api/central/register', $this->payload(), $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.workspace.slug', 'acme')
            ->assertJsonPath('data.workspace.name', 'Acme');

        $this->assertSame(64, strlen($response->json('data.handoff_code')));

        $user = User::where('email', 'ada@example.com')->sole();
        $tenant = Tenant::where('slug', 'acme')->sole();
        $this->assertSame($tenant->id, $response->json('data.workspace.id'));
        $this->assertSame(Role::Owner, $user->membershipIn($tenant)->role);
        $this->assertDatabaseHas('login_handoffs', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'code_hash' => hash('sha256', $response->json('data.handoff_code')),
        ]);
    }

    public function test_existing_email_with_correct_password_adds_a_second_workspace(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'existing-password']);
        $firstTenant = Tenant::factory()->create();
        $firstTenant->users()->attach($user, ['role' => Role::Owner]);

        $this->postJson('/api/central/register', [
            'email' => 'ada@example.com',
            'password' => 'existing-password',
            'workspace_name' => 'Second',
            'workspace_slug' => 'second',
        ], $this->apiHeaders())->assertCreated();

        $this->assertSame(2, $user->tenants()->count());
        $this->assertSame(1, User::count());
    }

    public function test_existing_email_with_wrong_password_returns_422_and_creates_nothing(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'existing-password']);

        $this->postJson('/api/central/register', $this->payload(['password' => 'wrong-password']), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => "This email already has an account. Enter that account's password to create another workspace.",
            ]);

        $this->assertSame(0, Tenant::count());
        $this->assertSame(0, Membership::count());
        $this->assertSame(0, LoginHandoff::count());
    }

    #[TestWith(['admin', 'This URL is reserved.'])]
    #[TestWith(['-bad', 'The workspace URL must be 3-40 lowercase letters, numbers or hyphens, and cannot start or end with a hyphen.'])]
    public function test_unavailable_slug_returns_422(string $slug, string $message): void
    {
        $this->postJson('/api/central/register', $this->payload(['workspace_slug' => $slug]), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workspace_slug' => $message]);
    }

    public function test_taken_slug_returns_422(): void
    {
        Tenant::factory()->create(['slug' => 'acme']);

        $this->postJson('/api/central/register', $this->payload(), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workspace_slug' => 'This URL is already taken.']);
    }

    public function test_new_email_requires_name_and_confirmed_password(): void
    {
        $this->postJson('/api/central/register', $this->payload(['name' => '', 'password_confirmation' => 'different']), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'password']);

        $this->assertSame(0, User::count());
    }

    public function test_sixth_attempt_in_a_minute_returns_429(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/central/register', [], $this->apiHeaders())->assertUnprocessable();
        }

        $this->postJson('/api/central/register', [], $this->apiHeaders())->assertTooManyRequests();
    }
}
