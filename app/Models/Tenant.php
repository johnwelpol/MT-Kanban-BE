<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\HasScopedValidationRules;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A workspace. Single-database tenancy: tenant rows are scoped by `tenant_id`.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $status
 */
class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasScopedValidationRules;

    /**
     * Columns stored as real columns instead of inside the `data` JSON column.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'slug', 'name', 'status', 'created_at', 'updated_at'];
    }

    /**
     * @return BelongsToMany<User, $this, Membership>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
