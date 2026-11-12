<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user loaded through `tenant()->users()`, so `pivot` is their membership.
 *
 * @mixin User
 *
 * @property Membership $pivot
 */
class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource),
            'role' => $this->pivot->role,
            'joined_at' => $this->pivot->created_at,
        ];
    }
}
