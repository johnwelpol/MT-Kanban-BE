<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Resources\InvitationResource;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Support\FrontendUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(Permission::InvitationsManage->value);

        return InvitationResource::collection(Invitation::query()->pending()->with('inviter')->latest()->get());
    }

    /**
     * Replaces any pending invitation for the same email, so only the newest link works.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::InvitationsManage->value);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);
        $role = Role::from($validated['role']);
        $inviter = $request->user();

        abort_unless($inviter->currentRole()->canAssign($role), 403);

        if (tenant()->users()->where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already a member of this workspace.']);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($validated, $role, $inviter, $token): Invitation {
            Invitation::whereNull('accepted_at')->where('email', $validated['email'])->delete();

            return Invitation::create([
                'email' => $validated['email'],
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(7),
            ]);
        });

        Mail::to($invitation->email)->queue(new InvitationMail(
            tenant('name'),
            $inviter->name,
            $role,
            FrontendUrl::tenant(tenant('slug'), "/invite/{$token}"),
        ));

        return (new InvitationResource($invitation->setRelation('inviter', $inviter)))->response()->setStatusCode(201);
    }

    public function destroy(Invitation $invitation): Response
    {
        Gate::authorize(Permission::InvitationsManage->value);

        $invitation->delete();

        return response()->noContent();
    }
}
