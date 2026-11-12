<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requires `assignee` and `comments_count` loaded (Card::withSummary()).
 *
 * @mixin Card
 */
class CardSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'board_id' => $this->board_id,
            'column_id' => $this->board_column_id,
            'title' => $this->title,
            'position' => $this->position,
            'assignee' => $this->assignee === null ? null : new UserResource($this->assignee),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'comments_count' => (int) $this->comments_count,
        ];
    }
}
