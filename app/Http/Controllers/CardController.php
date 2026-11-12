<?php

namespace App\Http\Controllers;

use App\Actions\Boards\AddCard;
use App\Enums\Permission;
use App\Http\Requests\Boards\StoreCardRequest;
use App\Http\Requests\Boards\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\CardSummaryResource;
use App\Models\Board;
use App\Models\Card;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CardController extends Controller
{
    public function __construct(private BoardNotifier $notifier) {}

    public function store(StoreCardRequest $request, Board $board, AddCard $addCard): JsonResponse
    {
        $column = $board->columns()->findOrFail($request->validated('column_id'));

        $card = $addCard->handle($column, $request->validated('title'), $request->user());

        $this->notifier->changed($board, 'card.created', ['card_id' => $card->id, 'column_id' => $column->id]);

        return (new CardSummaryResource($card->load('assignee')->loadCount('comments')))->response()->setStatusCode(201);
    }

    public function show(Board $board, Card $card): CardResource
    {
        Gate::authorize(Permission::BoardView->value);

        return new CardResource($this->loadDetails($card));
    }

    public function update(UpdateCardRequest $request, Board $board, Card $card): CardResource
    {
        $card->update($request->validated());

        $this->notifier->changed($board, 'card.updated', ['card_id' => $card->id]);

        return new CardResource($this->loadDetails($card));
    }

    public function destroy(Board $board, Card $card): Response
    {
        Gate::authorize(Permission::CardManage->value);

        $card->delete();

        $this->notifier->changed($board, 'card.deleted', ['card_id' => $card->id]);

        return response()->noContent();
    }

    private function loadDetails(Card $card): Card
    {
        return $card->load(['assignee', 'creator', 'comments.author'])->loadCount('comments');
    }
}
