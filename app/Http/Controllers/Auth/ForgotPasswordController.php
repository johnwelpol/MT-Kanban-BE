<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Only members of this workspace get a link. Always 202 so emails cannot be enumerated.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];

        if (User::where('email', $email)->first()?->belongsToTenant(tenant())) {
            Password::sendResetLink(['email' => $email]);
        }

        return response()->json(['data' => ['sent' => true]], 202);
    }
}
