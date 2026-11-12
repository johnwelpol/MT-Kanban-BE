<?php

namespace App\Http\Controllers;

use App\Actions\Boards\MoveColumn;
use App\Http\Requests\Boards\MoveColumnRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;

class MoveColumnController extends Controller
{
    public function __invoke(MoveColumnRequest $request, Board $board, BoardColumn $column, MoveColumn $moveColumn, BoardNotifier $notifier): JsonResponse
    {
        $columnIds = $moveColumn->handle($column, (int) $request->validated('position'));

        $notifier->changed($board, 'column.moved', ['column_id' => $column->id]);

        return response()->json(['data' => ['column_ids' => $columnIds]]);
    }
}
