<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TenantTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Unknown email, wrong password and non-member all fail with the same message.
     */
    public function __invoke(Request $request, TenantTokenIssuer $tokenIssuer): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password) || ! $user->belongsToTenant(tenant())) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return response()->json(['data' => [
            'token' => $tokenIssuer->issue($user, tenant()),
            'user' => new UserResource($user),
        ]]);
    }
}
