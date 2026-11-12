<?php

namespace Tests\Feature\Http\Controllers\Central;

use App\Enums\Role;
use App\Mail\WorkspaceListMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FindWorkspacesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['frontend.root_domain' => 'localhost', 'frontend.protocol' => 'http', 'frontend.port' => '3000']);
    }

    public function test_existing_email_queues_list_of_active_workspaces(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $user->tenants()->attach(Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme']), ['role' => Role::Owner]);
        $user->tenants()->attach(Tenant::factory()->suspended()->create(), ['role' => Role::Member]);

        $this->postJson('/api/central/find-workspaces', ['email' => 'ada@example.com'], $this->apiHeaders())
            ->assertAccepted()
            ->assertExactJson(['data' => ['sent' => true]]);

        Mail::assertQueued(WorkspaceListMail::class, fn (WorkspaceListMail $mail) => $mail->hasTo('ada@example.com')
            && $mail->queue === 'mail'
            && $mail->workspaces === [['name' => 'Acme', 'url' => 'http://acme.localhost:3000/login']]);
    }

    public function test_unknown_email_queues_nothing_and_returns_202(): void
    {
        $this->postJson('/api/central/find-workspaces', ['email' => 'nobody@example.com'], $this->apiHeaders())
            ->assertAccepted()
            ->assertExactJson(['data' => ['sent' => true]]);

        Mail::assertNothingQueued();
    }

    public function test_workspace_list_mail_renders_each_login_link(): void
    {
        $mail = new WorkspaceListMail([['name' => 'Acme', 'url' => 'http://acme.localhost:3000/login']]);

        $mail->assertSeeInHtml('http://acme.localhost:3000/login');
        $mail->assertSeeInText('Acme');
    }
}
