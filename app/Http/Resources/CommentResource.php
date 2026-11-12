<?php

namespace App\Http\Resources;

use App\Models\CardComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requires `author` loaded.
 *
 * @mixin CardComment
 */
class CommentResource extends JsonResource
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
            'card_id' => $this->card_id,
            'body' => $this->body,
            'author' => new UserResource($this->author),
            'created_at' => $this->created_at,
        ];
    }
}
