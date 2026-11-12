<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_an_admin_and_the_link_is_emailed(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        [$owner, $token] = $this->memberOf($tenant, Role::Owner);

        $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'admin'], $this->apiHeaders($tenant, $token))
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.invited_by.id', $owner->id)
            ->assertJsonStructure(['data' => ['id', 'email', 'role', 'invited_by', 'expires_at', 'created_at']]);

        Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('new@example.com')
            && $mail->role === Role::Admin
            && str_starts_with($mail->url, 'http://acme.localhost:3000/invite/'));
    }

    public function test_admin_cannot_invite_an_admin(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);

        $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'admin'], $this->apiHeaders($tenant, $token))
            ->assertForbidden();

        Mail::assertNothingQueued();
    }

    public function test_member_cannot_invite(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);

        $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'viewer'], $this->apiHeaders($tenant, $token))
            ->assertForbidden();
    }

    public function test_inviting_an_existing_member_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Owner);
        [$member] = $this->memberOf($tenant, Role::Viewer);

        $this->postJson('/api/invitations', ['email' => $member->email, 'role' => 'member'], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This person is already a member of this workspace.']);
    }

    public function test_reinviting_replaces_the_pending_invitation(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Owner);
        $headers = $this->apiHeaders($tenant, $token);

        $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'member'], $headers)->assertCreated();
        $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'viewer'], $headers)->assertCreated();

        $urls = Mail::queued(InvitationMail::class)->map(fn (InvitationMail $mail) => $mail->url);
        $this->assertCount(2, $urls);
        $this->assertSame(1, Invitation::count());

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/invites/'.basename($urls[0]), $this->apiHeaders($tenant))->assertNotFound();
        $this->getJson('/api/invites/'.basename($urls[1]), $this->apiHeaders($tenant))->assertOk();
    }

    public function test_index_lists_only_pending_invitations(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        $pending = Invitation::factory()->for($tenant)->create();
        Invitation::factory()->for($tenant)->expired()->create();
        Invitation::factory()->for($tenant)->accepted()->create();
        Invitation::factory()->for(Tenant::factory()->create())->create();

        $this->getJson('/api/invitations', $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$pending->id])
            ->assertJsonPath('data.0.invited_by.id', $pending->invited_by);
    }

    public function test_destroy_deletes_the_invitation(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        $invitation = Invitation::factory()->for($tenant)->create();

        $this->deleteJson("/api/invitations/{$invitation->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();

        $this->assertNull(Invitation::find($invitation->id));
    }

    public function test_invitation_of_another_workspace_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        $invitation = Invitation::factory()->for(Tenant::factory()->create())->create();

        $this->deleteJson("/api/invitations/{$invitation->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNotFound();
    }
}
