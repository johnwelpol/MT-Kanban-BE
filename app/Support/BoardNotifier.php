<?php

namespace App\Support;

use App\Models\Board;

/**
 * Tells other viewers of a board that it changed.
 */
class BoardNotifier
{
    /**
     * shortcut: no-op until realtime broadcasting lands (plan milestone "Realtime").
     *
     * @param  string  $type  one of the event types, e.g. `card.moved`
     * @param  array{card_id?: string, column_id?: string}  $extra
     */
    public function changed(Board|string $board, string $type, array $extra = []): void {}
}
