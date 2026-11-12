<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Support\Facades\DB;

class AddColumn
{
    /**
     * Append a column at the end of the board.
     */
    public function handle(Board $board, string $name): BoardColumn
    {
        return DB::transaction(function () use ($board, $name): BoardColumn {
            Board::whereKey($board->id)->lockForUpdate()->first();

            $lastPosition = BoardColumn::where('board_id', $board->id)->max('position');

            return $board->columns()->create([
                'name' => $name,
                'position' => $lastPosition === null ? 0 : $lastPosition + 1,
            ]);
        });
    }
}
