<?php

namespace App\Http\Controllers;

use App\Actions\Boards\CreateBoard;
use App\Enums\Permission;
use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\BoardSummaryResource;
use App\Models\Board;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BoardController extends Controller
{
    public function __construct(private BoardNotifier $notifier) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(Permission::BoardView->value);

        return BoardSummaryResource::collection(Board::withCount('cards')->orderBy('name')->get());
    }

    public function store(StoreBoardRequest $request, CreateBoard $createBoard): JsonResponse
    {
        $board = $createBoard->handle($request->user(), $request->validated('name'), $request->validated('description'));

        return (new BoardResource($board))->response()->setStatusCode(201);
    }

    public function show(Board $board): BoardResource
    {
        Gate::authorize(Permission::BoardView->value);

        return new BoardResource($board->load(['columns.cards' => fn ($query) => $query->withSummary()]));
    }

    public function update(UpdateBoardRequest $request, Board $board): BoardSummaryResource
    {
        $board->update($request->validated());

        $this->notifier->changed($board, 'board.updated');

        return new BoardSummaryResource($board->loadCount('cards'));
    }

    public function destroy(Board $board): Response
    {
        Gate::authorize(Permission::BoardDelete->value);

        $board->delete();

        $this->notifier->changed($board->id, 'board.deleted');

        return response()->noContent();
    }
}
