<?php

namespace App\Models;

use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 */
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['name', 'description', 'created_by'];

    /**
     * @return HasMany<BoardColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
