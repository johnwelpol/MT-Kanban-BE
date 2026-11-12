<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Tenant;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class BoardColumnControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    public function test_store_appends_column_with_no_cards(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 0, 'Done' => 0]);

        $response = $this->postJson("/api/boards/{$board->id}/columns", ['name' => 'Review'], $this->apiHeaders($tenant, $token))
            ->assertCreated()
            ->assertJsonPath('data.board_id', $board->id)
            ->assertJsonPath('data.name', 'Review')
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.cards', []);

        $this->assertSame(2, BoardColumn::find($response->json('data.id'))->position);
    }

    public function test_store_on_an_empty_board_starts_at_position_0(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = Board::factory()->for($tenant)->create();

        $this->postJson("/api/boards/{$board->id}/columns", ['name' => 'First'], $this->apiHeaders($tenant, $token))
            ->assertCreated()
            ->assertJsonPath('data.position', 0);
    }

    public function test_update_renames_column_and_returns_its_cards(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 2]);
        $column = $board->columns[0];

        $this->patchJson("/api/boards/{$board->id}/columns/{$column->id}", ['name' => 'Backlog'], $this->apiHeaders($tenant, $token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Backlog')
            ->assertJsonPath('data.cards.*.id', $column->cards->pluck('id')->all())
            ->assertJsonPath('data.cards.0.comments_count', 0);
    }

    public function test_update_requires_a_name_of_at_most_60_characters(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 0]);

        $this->patchJson("/api/boards/{$board->id}/columns/{$board->columns[0]->id}", ['name' => str_repeat('a', 61)], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field must not be greater than 60 characters.']);
    }

    public function test_destroy_deletes_column_and_its_cards(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 2, 'Done' => 1]);
        $column = $board->columns[0];

        $this->deleteJson("/api/boards/{$board->id}/columns/{$column->id}", [], $this->apiHeaders($tenant, $token))
            ->assertNoContent();

        $this->assertNull(BoardColumn::find($column->id));
        $this->assertSame(1, Card::count());
    }

    public function test_column_of_another_board_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['To do' => 0]);
        $otherBoard = $this->boardWithCards($tenant, ['To do' => 0]);

        $this->patchJson("/api/boards/{$board->id}/columns/{$otherBoard->columns[0]->id}", ['name' => 'Hijacked'], $this->apiHeaders($tenant, $token))
            ->assertNotFound();

        $this->assertSame('To do', BoardColumn::find($otherBoard->columns[0]->id)->name);
    }

    /**
     * @param  Closure(Board, BoardColumn): array{0: string, 1: string, 2: array<string, mixed>}  $request
     */
    #[DataProvider('writeRequests')]
    public function test_viewer_gets_403_on_writes(Closure $request): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Viewer);
        $board = $this->boardWithCards($tenant, ['To do' => 0]);
        [$method, $uri, $payload] = $request($board, $board->columns[0]);

        $this->json($method, $uri, $payload, $this->apiHeaders($tenant, $token))->assertForbidden();
    }

    /**
     * @return array<string, array{0: Closure(Board, BoardColumn): array{0: string, 1: string, 2: array<string, mixed>}}>
     */
    public static function writeRequests(): array
    {
        return [
            'store' => [fn (Board $board, BoardColumn $column) => ['POST', "/api/boards/{$board->id}/columns", ['name' => 'New']]],
            'update' => [fn (Board $board, BoardColumn $column) => ['PATCH', "/api/boards/{$board->id}/columns/{$column->id}", ['name' => 'Renamed']]],
            'destroy' => [fn (Board $board, BoardColumn $column) => ['DELETE', "/api/boards/{$board->id}/columns/{$column->id}", []]],
            'move' => [fn (Board $board, BoardColumn $column) => ['POST', "/api/boards/{$board->id}/columns/{$column->id}/move", ['position' => 0]]],
        ];
    }
}
