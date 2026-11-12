<?php

namespace App\Models;

use Database\Factories\CardCommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $card_id
 * @property int $user_id
 * @property string $body
 */
class CardComment extends Model
{
    /** @use HasFactory<CardCommentFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['card_id', 'user_id', 'body'];

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
