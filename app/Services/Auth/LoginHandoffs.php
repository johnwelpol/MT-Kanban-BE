<?php

namespace App\Services\Auth;

use App\Models\LoginHandoff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginHandoffs
{
    /**
     * Create a 120-second, single-use code. Only its SHA-256 hash is stored.
     */
    public function create(User $user, Tenant $tenant): string
    {
        $code = Str::random(64);

        LoginHandoff::create([
            'code_hash' => hash('sha256', $code),
            'user_id' => $user->id,
            'tenant_id' => $tenant->getTenantKey(),
            'expires_at' => now()->addSeconds(120),
        ]);

        return $code;
    }

    /**
     * Consume a code for this tenant. Returns null when the code is unknown, used, expired,
     * issued for another tenant, or its user is no longer a member.
     */
    public function redeem(string $code, Tenant $tenant): ?User
    {
        return DB::transaction(function () use ($code, $tenant): ?User {
            $handoff = LoginHandoff::where('code_hash', hash('sha256', $code))
                ->where('tenant_id', $tenant->getTenantKey())
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($handoff === null) {
                return null;
            }

            $handoff->forceFill(['used_at' => now()])->save();

            $user = $handoff->user;

            return $user->belongsToTenant($tenant) ? $user : null;
        });
    }
}
