<?php

namespace Tests\Support;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Tenant;

trait KanbanFixtures
{
    /**
     * Create a board whose columns and cards have explicit tenant_id and contiguous positions.
     *
     * @param  array<string, int>  $columns  column name => number of cards
     */
    protected function boardWithCards(Tenant $tenant, array $columns = ['To do' => 2, 'In progress' => 1, 'Done' => 0]): Board
    {
        $board = Board::factory()->for($tenant)->create();

        $columnPosition = 0;

        foreach ($columns as $columnName => $cardCount) {
            $column = BoardColumn::factory()->for($tenant)->for($board)->create([
                'name' => $columnName,
                'position' => $columnPosition++,
            ]);

            for ($cardPosition = 0; $cardPosition < $cardCount; $cardPosition++) {
                Card::factory()->for($tenant)->for($board)->for($column, 'column')->create(['position' => $cardPosition]);
            }
        }

        return $board->load('columns.cards');
    }
}
