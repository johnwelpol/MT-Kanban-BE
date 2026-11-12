<?php

namespace App\Http\Controllers;

use App\Actions\Boards\MoveCard;
use App\Http\Requests\Boards\MoveCardRequest;
use App\Models\Board;
use App\Models\Card;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;

class MoveCardController extends Controller
{
    public function __invoke(MoveCardRequest $request, Board $board, Card $card, MoveCard $moveCard, BoardNotifier $notifier): JsonResponse
    {
        $targetColumn = $board->columns()->findOrFail($request->validated('column_id'));

        $affectedColumns = $moveCard->handle($card, $targetColumn, (int) $request->validated('position'));

        $notifier->changed($board, 'card.moved', ['card_id' => $card->id]);

        return response()->json(['data' => ['columns' => $affectedColumns]]);
    }
}
