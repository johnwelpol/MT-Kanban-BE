<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bearer token must have been issued for this tenant, and its user must still be a member.
 */
class EnsureTenantMember
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * @var ?User $user
         */
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->tenant_id !== tenant()->getTenantKey()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $membership = $user->membershipIn(tenant());

        if ($membership === null) {
            return response()->json(['message' => 'You are not a member of this workspace.'], 403);
        }

        $user->setCurrentMembership($membership);

        Log::withContext(['tenant_id' => tenant()->getTenantKey(), 'user_id' => $user->id]);

        return $next($request);
    }
}
