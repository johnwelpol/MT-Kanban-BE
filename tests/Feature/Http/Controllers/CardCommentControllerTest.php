<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class CardCommentControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: Card}
     */
    private function tenantWithCard(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, $this->boardWithCards($tenant, ['To do' => 1])->columns[0]->cards[0]];
    }

    public function test_store_adds_a_comment_by_the_current_user(): void
    {
        [$tenant, $card] = $this->tenantWithCard();
        [$user, $token] = $this->memberOf($tenant, Role::Member);

        $response = $this->postJson("/api/boards/{$card->board_id}/cards/{$card->id}/comments", ['body' => 'Looks good'], $this->apiHeaders($tenant, $token))
            ->assertCreated()
            ->assertJsonPath('data.card_id', $card->id)
            ->assertJsonPath('data.body', 'Looks good')
            ->assertJsonPath('data.author', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->assertJsonStructure(['data' => ['id', 'card_id', 'body', 'author', 'created_at']]);

        $this->assertSame($tenant->id, CardComment::find($response->json('data.id'))->tenant_id);
    }

    public function test_store_requires_a_body(): void
    {
        [$tenant, $card] = $this->tenantWithCard();
        [, $token] = $this->memberOf($tenant, Role::Member);

        $this->postJson("/api/boards/{$card->board_id}/cards/{$card->id}/comments", ['body' => ''], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'The body field is required.']);
    }

    public function test_author_can_delete_their_own_comment(): void
    {
        [$tenant, $card] = $this->tenantWithCard();
        [$author, $token] = $this->memberOf($tenant, Role::Member);
        $comment = CardComment::factory()->for($tenant)->for($card)->create(['user_id' => $author->id]);

        $this->deleteJson("/api/boards/{$card->board_id}/cards/{$card->id}/comments/{$comment->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();

        $this->assertNull(CardComment::find($comment->id));
    }

    public function test_member_cannot_delete_another_users_comment(): void
    {
        [$tenant, $card] = $this->tenantWithCard();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $comment = CardComment::factory()->for($tenant)->for($card)->create();

        $this->deleteJson("/api/boards/{$card->board_id}/cards/{$card->id}/comments/{$comment->id}", [], $this->apiHeaders($tenant, $token))
            ->assertForbidden();

        $this->assertNotNull(CardComment::find($comment->id));
    }

    public function test_admin_can_delete_any_comment(): void
    {
        [$tenant, $card] = $this->tenantWithCard();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        $comment = CardComment::factory()->for($tenant)->for($card)->create();

        $this->deleteJson("/api/boards/{$card->board_id}/cards/{$card->id}/comments/{$comment->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();
    }

    public function test_comment_of_another_card_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        [$author, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 2]);
        [$card, $otherCard] = $board->columns[0]->cards;
        $comment = CardComment::factory()->for($tenant)->for($otherCard)->create(['user_id' => $author->id]);

        $this->deleteJson("/api/boards/{$board->id}/cards/{$card->id}/comments/{$comment->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNotFound();
    }
}
