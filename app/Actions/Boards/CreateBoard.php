<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateBoard
{
    /**
     * Create a board with its columns at positions 0..n-1 in the current tenant.
     *
     * @param  list<string>  $columns
     */
    public function handle(User $creator, string $name, ?string $description = null, array $columns = ['To do', 'In progress', 'Done']): Board
    {
        if (! tenancy()->initialized) {
            throw new LogicException('CreateBoard requires an initialized tenant.');
        }

        return DB::transaction(function () use ($creator, $name, $description, $columns): Board {
            $board = Board::create([
                'name' => $name,
                'description' => $description,
                'created_by' => $creator->id,
            ]);

            foreach (array_values($columns) as $position => $columnName) {
                $board->columns()->create(['name' => $columnName, 'position' => $position]);
            }

            return $board->load('columns');
        });
    }
}
