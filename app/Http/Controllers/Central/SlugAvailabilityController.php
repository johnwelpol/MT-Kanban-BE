<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Support\WorkspaceSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlugAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $slug = $request->validate(['slug' => ['required', 'string', 'max:255']])['slug'];
        $reason = WorkspaceSlug::check($slug);

        return response()->json(['data' => [
            'slug' => $slug,
            'available' => $reason === null,
            'reason' => $reason,
        ]]);
    }
}
