<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\HandoffRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginHandoffs;
use App\Services\Auth\TenantTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class HandoffController extends Controller
{
    public function __invoke(HandoffRequest $request, LoginHandoffs $handoffs, TenantTokenIssuer $tokenIssuer): JsonResponse
    {
        $code = $request->input('code');

        $user = $handoffs->redeem($code, tenant());

        if ($user === null) {
            throw ValidationException::withMessages(['code' => 'This sign-in link is invalid or has expired.']);
        }

        return response()->json(['data' => [
            'token' => $tokenIssuer->issue($user, tenant()),
            'user' => new UserResource($user),
        ]]);
    }
}
