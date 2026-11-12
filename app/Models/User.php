<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Membership for the current request's tenant, set by EnsureTenantMember.
     */
    private ?Membership $currentMembership = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Tenant, $this, Membership>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function membershipIn(Tenant $tenant): ?Membership
    {
        return Membership::where('tenant_id', $tenant->getTenantKey())
            ->where('user_id', $this->id)
            ->first();
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return Membership::where('tenant_id', $tenant->getTenantKey())
            ->where('user_id', $this->id)
            ->exists();
    }

    public function setCurrentMembership(Membership $membership): void
    {
        $this->currentMembership = $membership;
    }

    public function currentRole(): ?Role
    {
        return $this->currentMembership?->role;
    }
}
