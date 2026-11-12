<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $board_id
 * @property string $board_column_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property int|null $assignee_id
 * @property Carbon|null $due_date
 * @property int|null $created_by
 */
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['board_id', 'board_column_id', 'title', 'description', 'position', 'assignee_id', 'due_date', 'created_by'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_date' => 'date',
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
     * @return BelongsTo<BoardColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CardComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->orderBy('created_at');
    }

    /**
     * Everything CardSummaryResource reads, so rendering a column never lazy-loads per card.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withSummary(Builder $query): void
    {
        $query->with('assignee')->withCount('comments');
    }
}
