<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;

class TenantTokenIssuer
{
    /**
     * Issue a 7-day Sanctum token that only works for the given tenant.
     */
    public function issue(User $user, Tenant $tenant): string
    {
        $newToken = $user->createToken('web', ['*'], now()->addDays(7));
        $newToken->accessToken->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();

        return $newToken->plainTextToken;
    }
}
