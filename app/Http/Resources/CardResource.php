<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;

/**
 * Requires `assignee`, `creator`, `comments.author` and `comments_count` loaded.
 *
 * @mixin Card
 */
class CardResource extends CardSummaryResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'created_by' => $this->creator === null ? null : new UserResource($this->creator),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'comments' => CommentResource::collection($this->comments),
        ];
    }
}
