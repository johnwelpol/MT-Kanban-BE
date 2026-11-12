<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single-use code that carries a sign-in from the root host to a tenant host. Central table.
 *
 * @property string $id
 * @property string $code_hash
 * @property int $user_id
 * @property string $tenant_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class LoginHandoff extends Model
{
    use HasUuids, Prunable;

    public const UPDATED_AT = null;

    protected $fillable = ['code_hash', 'user_id', 'tenant_id', 'expires_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
