<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Auth\TenantTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = $this->findPending($token);

        return response()->json(['data' => [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'workspace' => ['name' => tenant('name'), 'slug' => tenant('slug')],
            'inviter_name' => $invitation->inviter->name,
            'user_exists' => User::where('email', $invitation->email)->exists(),
        ]]);
    }

    /**
     * Existing accounts confirm with their password; new ones pick a name and password.
     */
    public function accept(Request $request, string $token, TenantTokenIssuer $tokenIssuer): JsonResponse
    {
        $invitation = $this->findPending($token);
        $user = User::where('email', $invitation->email)->first();
        $newAccount = [];

        if ($user !== null) {
            $password = $request->validate(['password' => ['required', 'string']])['password'];

            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages(['password' => __('auth.password')]);
            }

            abort_if($user->belongsToTenant(tenant()), 409, 'You are already a member of this workspace.');
        } else {
            $newAccount = $request->validate([
                'name' => ['required', 'string', 'min:2', 'max:60'],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ]);
        }

        $user = DB::transaction(function () use ($invitation, $user, $newAccount): User {
            $user ??= User::create([...$newAccount, 'email' => $invitation->email]);
            tenant()->users()->attach($user, ['role' => $invitation->role]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        return response()->json(['data' => [
            'token' => $tokenIssuer->issue($user, tenant()),
            'user' => new UserResource($user),
        ]]);
    }

    /**
     * The tenant scope applies, so another workspace's token is a 404 too.
     */
    private function findPending(string $token): Invitation
    {
        return Invitation::pending()->with('inviter')->where('token_hash', hash('sha256', $token))->firstOrFail();
    }
}
