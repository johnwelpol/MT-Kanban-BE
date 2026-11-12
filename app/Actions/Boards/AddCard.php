<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddCard
{
    /**
     * Append a card at the bottom of the column.
     */
    public function handle(BoardColumn $column, string $title, User $creator): Card
    {
        return DB::transaction(function () use ($column, $title, $creator): Card {
            Board::whereKey($column->board_id)->lockForUpdate()->first();

            $lastPosition = Card::where('board_column_id', $column->id)->max('position');

            return Card::create([
                'board_id' => $column->board_id,
                'board_column_id' => $column->id,
                'title' => $title,
                'position' => $lastPosition === null ? 0 : $lastPosition + 1,
                'created_by' => $creator->id,
            ]);
        });
    }
}
