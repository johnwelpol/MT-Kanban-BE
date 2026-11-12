<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\BoardColumn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KanbanFixtures;
use Tests\TestCase;

class MoveColumnControllerTest extends TestCase
{
    use KanbanFixtures, RefreshDatabase;

    /**
     * @param  list<string>  $expectedOrder
     */
    #[DataProvider('moves')]
    public function test_move_reorders_columns_contiguously(string $movedColumn, int $position, array $expectedOrder): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0]);
        $idsByName = $board->columns->pluck('id', 'name');

        $response = $this->postJson(
            "/api/boards/{$board->id}/columns/{$idsByName[$movedColumn]}/move",
            ['position' => $position],
            $this->apiHeaders($tenant, $token),
        )->assertOk();

        $expectedIds = array_map(fn (string $name) => $idsByName[$name], $expectedOrder);
        $this->assertSame($expectedIds, $response->json('data.column_ids'));

        $storedColumns = BoardColumn::where('board_id', $board->id)->orderBy('position')->get();
        $this->assertSame($expectedIds, $storedColumns->pluck('id')->all());
        $this->assertSame([0, 1, 2, 3], $storedColumns->pluck('position')->all());
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: list<string>}>
     */
    public static function moves(): array
    {
        return [
            'first to last' => ['A', 3, ['B', 'C', 'D', 'A']],
            'last to first' => ['D', 0, ['D', 'A', 'B', 'C']],
            'into the middle' => ['A', 2, ['B', 'C', 'A', 'D']],
            'same position' => ['B', 1, ['A', 'B', 'C', 'D']],
            'past the end is clamped' => ['B', 99, ['A', 'C', 'D', 'B']],
        ];
    }

    public function test_negative_position_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->memberOf($tenant, Role::Member);
        $board = $this->boardWithCards($tenant, ['A' => 0]);

        $this->postJson("/api/boards/{$board->id}/columns/{$board->columns[0]->id}/move", ['position' => -1], $this->apiHeaders($tenant, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position' => 'The position field must be at least 0.']);
    }
}
