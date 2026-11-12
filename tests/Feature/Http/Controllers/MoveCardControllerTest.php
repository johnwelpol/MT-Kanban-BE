<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Card;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class MoveCardControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    /**
     * Column A starts as [a0, a1, a2] and column B as [b0, b1].
     *
     * @param  array<string, list<string>>  $expectedAffected  column name => card labels, as returned
     * @param  list<string>  $expectedA
     * @param  list<string>  $expectedB
     */
    #[DataProvider('moves')]
    public function test_move_rewrites_positions_in_every_affected_column(string $movedCard, string $targetColumn, int $position, array $expectedAffected, array $expectedA, array $expectedB): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['A' => 3, 'B' => 2]);
        $columnIds = ['A' => $board->columns[0]->id, 'B' => $board->columns[1]->id];
        $cardIds = [];
        foreach (['A' => 'a', 'B' => 'b'] as $columnName => $prefix) {
            foreach ($board->columns->firstWhere('name', $columnName)->cards as $index => $card) {
                $cardIds[$prefix.$index] = $card->id;
            }
        }
        $toIds = fn (array $labels): array => array_map(fn (string $label) => $cardIds[$label], $labels);

        $response = $this->postJson(
            "/api/boards/{$board->id}/cards/{$cardIds[$movedCard]}/move",
            ['column_id' => $columnIds[$targetColumn], 'position' => $position],
            $this->apiHeaders($tenant, $token),
        )->assertOk();

        $expectedResponse = [];
        foreach ($expectedAffected as $columnName => $labels) {
            $expectedResponse[] = ['id' => $columnIds[$columnName], 'card_ids' => $toIds($labels)];
        }
        $this->assertSame($expectedResponse, $response->json('data.columns'));

        foreach (['A' => $expectedA, 'B' => $expectedB] as $columnName => $labels) {
            $storedCards = Card::where('board_column_id', $columnIds[$columnName])->orderBy('position')->get();
            $this->assertSame($toIds($labels), $storedCards->pluck('id')->all(), "Column {$columnName} order");
            $this->assertSame(range(0, count($labels) - 1), $storedCards->pluck('position')->all(), "Column {$columnName} positions");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: array<string, list<string>>, 4: list<string>, 5: list<string>}>
     */
    public static function moves(): array
    {
        return [
            'down within a column' => ['a0', 'A', 2, ['A' => ['a1', 'a2', 'a0']], ['a1', 'a2', 'a0'], ['b0', 'b1']],
            'up within a column' => ['a2', 'A', 0, ['A' => ['a2', 'a0', 'a1']], ['a2', 'a0', 'a1'], ['b0', 'b1']],
            'to the top of another column' => ['a1', 'B', 0, ['B' => ['a1', 'b0', 'b1'], 'A' => ['a0', 'a2']], ['a0', 'a2'], ['a1', 'b0', 'b1']],
            'to the middle of another column' => ['a1', 'B', 1, ['B' => ['b0', 'a1', 'b1'], 'A' => ['a0', 'a2']], ['a0', 'a2'], ['b0', 'a1', 'b1']],
            'to the end of another column' => ['a1', 'B', 2, ['B' => ['b0', 'b1', 'a1'], 'A' => ['a0', 'a2']], ['a0', 'a2'], ['b0', 'b1', 'a1']],
            'past the end is clamped' => ['a1', 'B', 99, ['B' => ['b0', 'b1', 'a1'], 'A' => ['a0', 'a2']], ['a0', 'a2'], ['b0', 'b1', 'a1']],
            'past the end within a column is clamped' => ['a0', 'A', 99, ['A' => ['a1', 'a2', 'a0']], ['a1', 'a2', 'a0'], ['b0', 'b1']],
        ];
    }

    public function test_move_into_a_column_of_another_board_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['A' => 1]);
        $otherBoard = $this->boardWithCards($tenant, ['X' => 0]);
        $card = $board->columns[0]->cards[0];

        $this->postJson("/api/boards/{$board->id}/cards/{$card->id}/move", ['column_id' => $otherBoard->columns[0]->id, 'position' => 0], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['column_id']);

        $this->assertSame($board->columns[0]->id, Card::find($card->id)->board_column_id);
    }
}
