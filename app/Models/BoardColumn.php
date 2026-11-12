<?php

namespace App\Models;

use Database\Factories\BoardColumnFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $board_id
 * @property string $name
 * @property int $position
 */
class BoardColumn extends Model
{
    /** @use HasFactory<BoardColumnFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $table = 'board_columns';

    protected $fillable = ['board_id', 'name', 'position'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'board_column_id')->orderBy('position');
    }
}
