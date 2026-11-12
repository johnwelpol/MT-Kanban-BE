<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Support\Facades\DB;

class MoveColumn
{
    /**
     * Move a column to a 0-based index (clamped) and rewrite every position to 0..n-1.
     *
     * @return list<string> column ids in their new order
     */
    public function handle(BoardColumn $column, int $position): array
    {
        return DB::transaction(function () use ($column, $position): array {
            Board::whereKey($column->board_id)->lockForUpdate()->first();

            $columnIds = BoardColumn::where('board_id', $column->board_id)
                ->whereKeyNot($column->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($columnIds, max(0, min($position, count($columnIds))), 0, [$column->id]);

            foreach ($columnIds as $index => $columnId) {
                BoardColumn::whereKey($columnId)->update(['position' => $index]);
            }

            return $columnIds;
        });
    }
}
