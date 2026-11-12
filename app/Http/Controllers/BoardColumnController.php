<?php

namespace App\Http\Controllers;

use App\Actions\Boards\AddColumn;
use App\Enums\Permission;
use App\Http\Requests\Boards\BoardColumnRequest;
use App\Http\Resources\ColumnResource;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BoardColumnController extends Controller
{
    public function __construct(private BoardNotifier $notifier) {}

    public function store(BoardColumnRequest $request, Board $board, AddColumn $addColumn): JsonResponse
    {
        $column = $addColumn->handle($board, $request->validated('name'));

        $this->notifier->changed($board, 'column.created', ['column_id' => $column->id]);

        return (new ColumnResource($column))->response()->setStatusCode(201);
    }

    public function update(BoardColumnRequest $request, Board $board, BoardColumn $column): ColumnResource
    {
        $column->update(['name' => $request->validated('name')]);

        $this->notifier->changed($board, 'column.updated', ['column_id' => $column->id]);

        return new ColumnResource($column->load(['cards' => fn ($query) => $query->withSummary()]));
    }

    /**
     * Deletes the column's cards too (database cascade).
     */
    public function destroy(Board $board, BoardColumn $column): Response
    {
        Gate::authorize(Permission::ColumnManage->value);

        $column->delete();

        $this->notifier->changed($board, 'column.deleted', ['column_id' => $column->id]);

        return response()->noContent();
    }
}
