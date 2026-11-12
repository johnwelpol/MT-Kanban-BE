<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreCardCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardComment;
use App\Support\BoardNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CardCommentController extends Controller
{
    public function __construct(private BoardNotifier $notifier) {}

    public function store(StoreCardCommentRequest $request, Board $board, Card $card): JsonResponse
    {
        $comment = $card->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $this->notifier->changed($board, 'comment.created', ['card_id' => $card->id]);

        return (new CommentResource($comment->setRelation('author', $request->user())))->response()->setStatusCode(201);
    }

    public function destroy(Board $board, Card $card, CardComment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        $this->notifier->changed($board, 'comment.deleted', ['card_id' => $card->id]);

        return response()->noContent();
    }
}
