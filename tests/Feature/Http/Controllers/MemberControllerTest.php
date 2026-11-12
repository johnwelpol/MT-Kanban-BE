<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Card;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    public function test_index_lists_owner_first_then_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        [$zed] = $this->memberOf($tenant, Role::Owner, User::factory()->create(['name' => 'Zed']));
        [$bob, $token] = $this->memberOf($tenant, Role::Viewer, User::factory()->create(['name' => 'Bob']));
        [$amy] = $this->memberOf($tenant, Role::Admin, User::factory()->create(['name' => 'Amy']));
        $this->memberOf(Tenant::factory()->create());

        $this->getJson('/api/members', $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.*.user.id', [$zed->id, $amy->id, $bob->id])
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonStructure(['data' => [['user' => ['id', 'name', 'email'], 'role', 'joined_at']]]);
    }

    #[DataProvider('roleChanges')]
    public function test_role_changes_follow_the_hierarchy(Role $actorRole, ?Role $targetRole, Role $newRole, int $expectedStatus): void
    {
        $tenant = Tenant::factory()->create();
        [$actor, $token] = $this->memberOf($tenant, $actorRole);
        $target = $targetRole === null ? $actor : $this->memberOf($tenant, $targetRole)[0];

        $this->patchJson("/api/members/{$target->id}", ['role' => $newRole->value], $this->apiHeaders($tenant, $token))
            ->assertStatus($expectedStatus);

        $expectedRole = $expectedStatus === 200 ? $newRole : ($targetRole ?? $actorRole);
        $this->assertSame($expectedRole, $target->membershipIn($tenant)->role);
    }

    /**
     * A null target role means the actor targets themselves.
     *
     * @return array<string, array{0: Role, 1: Role|null, 2: Role, 3: int}>
     */
    public static function roleChanges(): array
    {
        return [
            'owner demotes admin to member' => [Role::Owner, Role::Admin, Role::Member, 200],
            'admin changes another admin' => [Role::Admin, Role::Admin, Role::Member, 403],
            'admin promotes member to admin' => [Role::Admin, Role::Member, Role::Admin, 403],
            'admin changes the owner' => [Role::Admin, Role::Owner, Role::Member, 403],
            'owner promotes member to owner' => [Role::Owner, Role::Member, Role::Owner, 403],
            'owner changes themselves' => [Role::Owner, null, Role::Admin, 403],
            'member calls patch' => [Role::Member, Role::Viewer, Role::Viewer, 403],
        ];
    }

    public function test_update_returns_the_member(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        [$target] = $this->memberOf($tenant, Role::Viewer);

        $this->patchJson("/api/members/{$target->id}", ['role' => 'member'], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('data.role', 'member');
    }

    public function test_user_who_is_not_a_member_here_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Owner);
        [$outsider] = $this->memberOf(Tenant::factory()->create(), Role::Viewer);

        $this->patchJson("/api/members/{$outsider->id}", ['role' => 'member'], $this->apiHeaders($tenant, $token))
            ->assertNotFound();
        $this->deleteJson("/api/members/{$outsider->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNotFound();
    }

    public function test_removing_a_member_only_touches_this_workspace(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        [, $ownerToken] = $this->memberOf($tenant, Role::Owner);
        [$target, $targetToken] = $this->memberOf($tenant, Role::Member);
        [, $otherWorkspaceToken] = $this->memberOf($otherTenant, Role::Member, $target);
        $card = $this->boardWithCards($tenant, ['To do' => 1])->columns[0]->cards[0];
        $otherCard = $this->boardWithCards($otherTenant, ['To do' => 1])->columns[0]->cards[0];
        $card->update(['assignee_id' => $target->id]);
        $otherCard->update(['assignee_id' => $target->id]);

        $this->deleteJson("/api/members/{$target->id}", [], $this->apiHeaders($tenant, $ownerToken))
            ->assertNoContent();

        tenancy()->end();
        $this->assertFalse($target->belongsToTenant($tenant));
        $this->assertNull(Card::find($card->id)->assignee_id);
        $this->assertSame($target->id, Card::find($otherCard->id)->assignee_id);
        $this->assertNull(PersonalAccessToken::findToken($targetToken));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', $this->apiHeaders($otherTenant, $otherWorkspaceToken))->assertOk();
    }

    public function test_admin_cannot_remove_the_owner(): void
    {
        $tenant = Tenant::factory()->create();
        [$owner] = $this->memberOf($tenant, Role::Owner);
        [, $token] = $this->memberOf($tenant, Role::Admin);

        $this->deleteJson("/api/members/{$owner->id}", [], $this->apiHeaders($tenant, $token))
            ->assertForbidden();

        $this->assertTrue(Membership::where('tenant_id', $tenant->id)->where('user_id', $owner->id)->exists());
    }
}
