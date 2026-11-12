<?php

namespace App\Http\Controllers\Central;

use App\Actions\Workspaces\RegisterWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterWorkspaceRequest $request, RegisterWorkspace $registerWorkspace): JsonResponse
    {
        [$tenant, $handoffCode] = $registerWorkspace->handle($request->validated());

        return response()->json(['data' => [
            'workspace' => new WorkspaceResource($tenant),
            'handoff_code' => $handoffCode,
        ]], 201);
    }
}
