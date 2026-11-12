<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ResolveTenantController extends Controller
{
    /**
     * Resolve a workspace slug (from the request host) to its tenant id for the Next.js proxy.
     */
    public function __invoke(Request $request): WorkspaceResource|JsonResponse
    {
        $slug = $request->validate(['slug' => ['required', 'string', 'max:40']])['slug'];

        $tenant = Cache::remember("tenant-resolve:{$slug}", 60, fn () => Tenant::where('slug', $slug)->first());

        if (! $tenant?->isActive()) {
            return response()->json(['message' => 'Workspace not found.'], 404);
        }

        return new WorkspaceResource($tenant);
    }
}
