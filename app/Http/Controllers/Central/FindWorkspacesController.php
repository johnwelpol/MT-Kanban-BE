<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Mail\WorkspaceListMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FindWorkspacesController extends Controller
{
    /**
     * Email the account its workspace login URLs. Always 202 so emails cannot be enumerated.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];

        $workspaces = User::where('email', $email)->first()
            ?->tenants()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => ['name' => $tenant->name, 'url' => FrontendUrl::tenant($tenant->slug, '/login')])
            ->all() ?? [];

        if ($workspaces !== []) {
            Mail::to($email)->queue(new WorkspaceListMail($workspaces));
        }

        return response()->json(['data' => ['sent' => true]], 202);
    }
}
