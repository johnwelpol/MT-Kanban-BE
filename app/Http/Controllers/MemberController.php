<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Resources\MemberResource;
use App\Models\Card;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(Permission::MembersView->value);

        return MemberResource::collection(
            tenant()->users()->orderByRaw("tenant_user.role = 'owner' desc")->orderBy('name')->get()
        );
    }

    public function update(Request $request, User $user): MemberResource
    {
        $actorRole = $this->authorizeManaging($request->user(), $user);

        $role = Role::from($request->validate(['role' => ['required', Rule::enum(Role::class)]])['role']);
        abort_unless($actorRole->canAssign($role), 403);

        $this->membershipQuery($user)->update(['role' => $role->value]);

        return new MemberResource(tenant()->users()->whereKey($user->id)->first());
    }

    /**
     * Also unassigns their cards and revokes their tokens, in this workspace only.
     */
    public function destroy(Request $request, User $user): Response
    {
        $this->authorizeManaging($request->user(), $user);

        DB::transaction(function () use ($user): void {
            $this->membershipQuery($user)->delete();
            Card::where('assignee_id', $user->id)->update(['assignee_id' => null]);
            $user->tokens()->where('tenant_id', tenant()->getTenantKey())->delete();
        });

        return response()->noContent();
    }

    /**
     * 404 when the target is not a member here; 403 for self-targeting or a target of equal or higher rank.
     */
    private function authorizeManaging(User $actor, User $target): Role
    {
        Gate::authorize(Permission::MembersManage->value);

        $targetMembership = $target->membershipIn(tenant()) ?? abort(404);
        $actorRole = $actor->currentRole();

        abort_if($actor->is($target) || ! $actorRole->canManage($targetMembership->role), 403);

        return $actorRole;
    }

    /**
     * @return Builder<Membership>
     */
    private function membershipQuery(User $user): Builder
    {
        return Membership::where('tenant_id', tenant()->getTenantKey())->where('user_id', $user->id);
    }
}
