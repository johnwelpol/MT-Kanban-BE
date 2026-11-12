<?php

namespace App\Http\Resources;

use App\Models\BoardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoardColumn
 */
class ColumnResource extends JsonResource
{
    /**
     * Cards are only rendered when eager-loaded (with Card::withSummary()); otherwise the
     * column is new or empty in this response and `cards` is [].
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'board_id' => $this->board_id,
            'name' => $this->name,
            'position' => $this->position,
            'cards' => $this->relationLoaded('cards') ? CardSummaryResource::collection($this->cards) : [],
        ];
    }
}
