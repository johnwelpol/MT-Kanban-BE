<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\Role;
use App\Models\Tenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_gets_a_reset_link_to_the_workspace(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        [$user] = $this->memberOf($tenant, Role::Member);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email], $this->apiHeaders($tenant))
            ->assertStatus(202)
            ->assertExactJson(['data' => ['sent' => true]]);

        Notification::assertSentTo($user, ResetPassword::class, fn (ResetPassword $notification) => str_starts_with(
            $notification->toMail($user)->actionUrl,
            'http://acme.localhost:3000/reset-password?token='.$notification->token.'&email='.urlencode($user->email),
        ));
    }

    public function test_non_member_gets_nothing_but_the_same_response(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        [$user] = $this->memberOf(Tenant::factory()->create());

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email], $this->apiHeaders($tenant))
            ->assertStatus(202)
            ->assertExactJson(['data' => ['sent' => true]]);
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'], $this->apiHeaders($tenant))
            ->assertStatus(202);

        Notification::assertNothingSent();
    }

    public function test_fourth_request_in_a_minute_returns_429(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'], $this->apiHeaders($tenant))->assertStatus(202);
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'], $this->apiHeaders($tenant))->assertTooManyRequests();
    }
}
