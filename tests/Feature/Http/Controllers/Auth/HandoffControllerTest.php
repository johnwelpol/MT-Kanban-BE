<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\LoginHandoffs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class HandoffControllerTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID_MESSAGE = 'This sign-in link is invalid or has expired.';

    /**
     * @return array{0: User, 1: Tenant, 2: string}
     */
    private function ownerWithHandoff(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => Role::Owner]);

        return [$user, $tenant, app(LoginHandoffs::class)->create($user, $tenant)];
    }

    public function test_valid_code_returns_token_bound_to_the_tenant(): void
    {
        [$user, $tenant, $code] = $this->ownerWithHandoff();

        $response = $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.user', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

        $this->assertSame($tenant->id, PersonalAccessToken::findToken($response->json('data.token'))->tenant_id);
    }

    public function test_reused_code_returns_422(): void
    {
        [, $tenant, $code] = $this->ownerWithHandoff();
        $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($tenant))->assertOk();

        $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => self::INVALID_MESSAGE]);
    }

    public function test_expired_code_returns_422(): void
    {
        [, $tenant, $code] = $this->ownerWithHandoff();

        $this->travel(121)->seconds();

        $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => self::INVALID_MESSAGE]);
    }

    public function test_code_for_another_tenant_returns_422(): void
    {
        [$user, , $code] = $this->ownerWithHandoff();
        $otherTenant = Tenant::factory()->create();
        $otherTenant->users()->attach($user, ['role' => Role::Owner]);

        $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($otherTenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => self::INVALID_MESSAGE]);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_code_for_removed_member_returns_422(): void
    {
        [$user, $tenant, $code] = $this->ownerWithHandoff();
        Membership::where('tenant_id', $tenant->id)->where('user_id', $user->id)->delete();

        $this->postJson('/api/auth/handoff', ['code' => $code], $this->apiHeaders($tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => self::INVALID_MESSAGE]);
    }
}
