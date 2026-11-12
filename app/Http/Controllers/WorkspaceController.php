<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Resources\WorkspaceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function update(Request $request): WorkspaceResource
    {
        Gate::authorize(Permission::WorkspaceUpdate->value);

        $tenant = tenant();
        $tenant->update($request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]));

        Cache::forget("tenant-resolve:{$tenant->slug}");

        return new WorkspaceResource($tenant);
    }
}
