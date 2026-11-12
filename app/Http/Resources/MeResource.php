<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user with their role in the current workspace.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->currentRole();

        return [
            'user' => new UserResource($this->resource),
            'workspace' => new WorkspaceResource(tenant()),
            'role' => $role->value,
            'permissions' => array_map(fn (Permission $permission) => $permission->value, $role->permissions()),
        ];
    }
}
