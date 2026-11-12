<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's role in one workspace. Update roles with
 * `Membership::where('tenant_id', …)->where('user_id', …)->update([...])` (composite key, no id).
 *
 * @property string $tenant_id
 * @property int $user_id
 * @property Role $role
 */
class Membership extends Pivot
{
    protected $table = 'tenant_user';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }
}
