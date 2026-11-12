<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Tenant;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class BoardControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    public function test_index_lists_boards_by_name_with_card_counts(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Viewer);
        $roadmap = $this->boardWithCards($tenant, ['To do' => 2]);
        $roadmap->update(['name' => 'Roadmap']);
        Board::factory()->for($tenant)->create(['name' => 'Alpha']);

        $this->getJson('/api/boards', $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Alpha', 'Roadmap'])
            ->assertJsonPath('data.*.cards_count', [0, 2])
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'cards_count', 'created_at', 'updated_at']]]);
    }

    public function test_index_does_not_include_other_tenants_boards(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant);
        Board::factory()->for(Tenant::factory()->create())->create();

        $this->getJson('/api/boards', $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_store_creates_board_with_default_columns_and_returns_201(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Member);

        $this->postJson('/api/boards', ['name' => 'Roadmap', 'description' => 'Q3'], $this->apiHeaders($tenant, $token))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Roadmap')
            ->assertJsonPath('data.description', 'Q3')
            ->assertJsonPath('data.columns.*.name', ['To do', 'In progress', 'Done'])
            ->assertJsonPath('data.columns.*.position', [0, 1, 2])
            ->assertJsonPath('data.columns.*.cards', [[], [], []]);

        $this->assertSame($user->id, Board::sole()->created_by);
    }

    public function test_store_requires_a_name(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant);

        $this->postJson('/api/boards', ['name' => ''], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    }

    public function test_show_returns_columns_and_card_summaries_in_position_order(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $token] = $this->memberOf($tenant, Role::Viewer);
        $board = $this->boardWithCards($tenant, ['To do' => 2, 'Done' => 0]);
        $firstCard = $board->columns[0]->cards[0];
        $firstCard->update(['assignee_id' => $user->id, 'due_date' => '2026-12-01']);
        CardComment::factory()->for($tenant)->for($firstCard)->create();

        $this->getJson("/api/boards/{$board->id}", $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.id', $board->id)
            ->assertJsonPath('data.columns.*.name', ['To do', 'Done'])
            ->assertJsonPath('data.columns.0.cards.*.id', $board->columns[0]->cards->pluck('id')->all())
            ->assertJsonPath('data.columns.0.cards.0', [
                'id' => $firstCard->id,
                'board_id' => $board->id,
                'column_id' => $board->columns[0]->id,
                'title' => $firstCard->title,
                'position' => 0,
                'assignee' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'due_date' => '2026-12-01',
                'comments_count' => 1,
            ])
            ->assertJsonPath('data.columns.0.cards.1.assignee', null)
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'created_at', 'updated_at', 'columns' => [['id', 'board_id', 'name', 'position', 'cards']]]]);
    }

    public function test_show_returns_404_for_another_tenants_board(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant);
        $otherTenantBoard = Board::factory()->for(Tenant::factory()->create())->create();

        $this->getJson("/api/boards/{$otherTenantBoard->id}", $this->apiHeaders($tenant, $token))
            ->assertNotFound();
    }

    public function test_update_renames_board_and_returns_summary(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 1]);

        $this->patchJson("/api/boards/{$board->id}", ['name' => 'Renamed'], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.cards_count', 1)
            ->assertJsonMissingPath('data.columns');
    }

    public function test_admin_can_delete_board_and_its_cards(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Admin);
        $board = $this->boardWithCards($tenant, ['To do' => 2]);

        $this->deleteJson("/api/boards/{$board->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();

        $this->assertNull(Board::find($board->id));
        $this->assertSame(0, Card::count());
    }

    public function test_member_cannot_delete_board(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = Board::factory()->for($tenant)->create();

        $this->deleteJson("/api/boards/{$board->id}", [], $this->apiHeaders($tenant, $token))
            ->assertForbidden();

        $this->assertNotNull(Board::find($board->id));
    }

    /**
     * @param  Closure(Board): array{0: string, 1: string, 2: array<string, mixed>}  $request
     */
    #[DataProvider('writeRequests')]
    public function test_viewer_gets_403_on_writes(Closure $request): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Viewer);
        [$method, $uri, $payload] = $request(Board::factory()->for($tenant)->create());

        $this->json($method, $uri, $payload, $this->apiHeaders($tenant, $token))->assertForbidden();
    }

    /**
     * @return array<string, array{0: Closure(Board): array{0: string, 1: string, 2: array<string, mixed>}}>
     */
    public static function writeRequests(): array
    {
        return [
            'store' => [fn (Board $board) => ['POST', '/api/boards', ['name' => 'New']]],
            'update' => [fn (Board $board) => ['PATCH', "/api/boards/{$board->id}", ['name' => 'Renamed']]],
            'destroy' => [fn (Board $board) => ['DELETE', "/api/boards/{$board->id}", []]],
        ];
    }
}
