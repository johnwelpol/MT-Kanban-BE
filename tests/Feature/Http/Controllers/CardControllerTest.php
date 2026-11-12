<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    public function test_store_appends_card_to_the_column(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 2]);
        $column = $board->columns[0];

        $response = $this->postJson("/api/boards/{$board->id}/cards", ['column_id' => $column->id, 'title' => 'Write tests'], $this->apiHeaders($tenant, $token))
            ->assertCreated();

        $response->assertExactJson(['data' => [
            'id' => $response->json('data.id'),
            'board_id' => $board->id,
            'column_id' => $column->id,
            'title' => 'Write tests',
            'position' => 2,
            'assignee' => null,
            'due_date' => null,
            'comments_count' => 0,
        ]]);
        $this->assertSame($user->id, Card::find($response->json('data.id'))->created_by);
    }

    public function test_store_rejects_a_column_from_another_board(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 0]);
        $otherBoard = $this->boardWithCards($tenant, ['To do' => 0]);

        $this->postJson("/api/boards/{$board->id}/cards", ['column_id' => $otherBoard->columns[0]->id, 'title' => 'Sneaky'], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['column_id' => 'The selected column id is invalid.']);

        $this->assertSame(0, Card::count());
    }

    public function test_show_returns_card_details_with_comments_oldest_first(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Viewer);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        $card = $board->columns[0]->cards[0];
        $card->update(['created_by' => $user->id, 'description' => 'Details']);
        $newer = CardComment::factory()->for($tenant)->for($card)->create(['created_at' => now()]);
        $older = CardComment::factory()->for($tenant)->for($card)->create(['created_at' => now()->subHour()]);

        $this->getJson("/api/boards/{$board->id}/cards/{$card->id}", $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.id', $card->id)
            ->assertJsonPath('data.description', 'Details')
            ->assertJsonPath('data.created_by', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->assertJsonPath('data.comments_count', 2)
            ->assertJsonPath('data.comments.*.id', [$older->id, $newer->id])
            ->assertJsonStructure(['data' => [
                'id', 'board_id', 'column_id', 'title', 'position', 'assignee', 'due_date', 'comments_count',
                'description', 'created_by', 'created_at', 'updated_at',
                'comments' => [['id', 'card_id', 'body', 'author' => ['id', 'name', 'email'], 'created_at']],
            ]]);
    }

    public function test_card_requested_under_another_board_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant);
        $boardA = $this->boardWithCards($tenant, ['To do' => 1]);
        $boardB = $this->boardWithCards($tenant, ['To do' => 0]);

        $this->getJson("/api/boards/{$boardB->id}/cards/{$boardA->columns[0]->cards[0]->id}", $this->apiHeaders($tenant, $token))
            ->assertNotFound();
    }

    public function test_assigning_a_member_shows_them_on_the_board(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        [$assignee] = $this->memberOf($tenant, Role::Viewer);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        $card = $board->columns[0]->cards[0];

        $this->patchJson("/api/boards/{$board->id}/cards/{$card->id}", ['assignee_id' => $assignee->id, 'due_date' => '2026-12-24'], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $assignee->id)
            ->assertJsonPath('data.due_date', '2026-12-24');

        $this->getJson("/api/boards/{$board->id}", $this->apiHeaders($tenant, $token))
            ->assertJsonPath('data.columns.0.cards.0.assignee.id', $assignee->id);
    }

    public function test_assigning_a_non_member_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $outsider = User::factory()->create();
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        $card = $board->columns[0]->cards[0];

        $this->patchJson("/api/boards/{$board->id}/cards/{$card->id}", ['assignee_id' => $outsider->id], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id' => 'The assignee must be a member of this workspace.']);

        $this->assertNull(Card::find($card->id)->assignee_id);
    }

    public function test_assigning_a_member_of_another_workspace_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        [$otherWorkspaceMember] = $this->memberOf(Tenant::factory()->create());
        $board = $this->boardWithCards($tenant, ['To do' => 1]);

        $this->patchJson("/api/boards/{$board->id}/cards/{$board->columns[0]->cards[0]->id}", ['assignee_id' => $otherWorkspaceMember->id], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);
    }

    public function test_update_can_clear_assignee_and_due_date(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        $card = $board->columns[0]->cards[0];
        $card->update(['assignee_id' => $user->id, 'due_date' => '2026-12-24']);

        $this->patchJson("/api/boards/{$board->id}/cards/{$card->id}", ['assignee_id' => null, 'due_date' => null], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.due_date', null);
    }

    public function test_due_date_must_be_year_month_day(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);

        $this->patchJson("/api/boards/{$board->id}/cards/{$board->columns[0]->cards[0]->id}", ['due_date' => '24/12/2026'], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date' => 'The due date field must match the format Y-m-d.']);
    }

    public function test_destroy_deletes_the_card(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        $card = $board->columns[0]->cards[0];

        $this->deleteJson("/api/boards/{$board->id}/cards/{$card->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();

        $this->assertNull(Card::find($card->id));
    }

    /**
     * @param  Closure(Board, Card): array{0: string, 1: string, 2: array<string, mixed>}  $request
     */
    #[DataProvider('writeRequests')]
    public function test_viewer_gets_403_on_writes(Closure $request): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Viewer);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);
        [$method, $uri, $payload] = $request($board, $board->columns[0]->cards[0]);

        $this->json($method, $uri, $payload, $this->apiHeaders($tenant, $token))->assertForbidden();
    }

    /**
     * @return array<string, array{0: Closure(Board, Card): array{0: string, 1: string, 2: array<string, mixed>}}>
     */
    public static function writeRequests(): array
    {
        return [
            'store' => [fn (Board $board, Card $card) => ['POST', "/api/boards/{$board->id}/cards", ['column_id' => $card->board_column_id, 'title' => 'New']]],
            'update' => [fn (Board $board, Card $card) => ['PATCH', "/api/boards/{$board->id}/cards/{$card->id}", ['title' => 'Renamed']]],
            'destroy' => [fn (Board $board, Card $card) => ['DELETE', "/api/boards/{$board->id}/cards/{$card->id}", []]],
            'move' => [fn (Board $board, Card $card) => ['POST', "/api/boards/{$board->id}/cards/{$card->id}/move", ['column_id' => $card->board_column_id, 'position' => 0]]],
            'comment' => [fn (Board $board, Card $card) => ['POST', "/api/boards/{$board->id}/cards/{$card->id}/comments", ['body' => 'Hi']]],
        ];
    }
}
