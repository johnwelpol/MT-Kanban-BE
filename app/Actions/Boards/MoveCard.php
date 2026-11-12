<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use Illuminate\Support\Facades\DB;

class MoveCard
{
    /**
     * Move a card to a 0-based index (clamped) in the target column. Positions in every
     * affected column are rewritten to 0..n-1.
     *
     * @return list<array{id: string, card_ids: list<string>}> the affected columns, target first
     */
    public function handle(Card $card, BoardColumn $target, int $position): array
    {
        return DB::transaction(function () use ($card, $target, $position): array {
            Board::whereKey($card->board_id)->lockForUpdate()->first();

            $sourceColumnId = $card->board_column_id;

            $targetCardIds = $this->orderedCardIds($target->id, exceptCardId: $card->id);
            array_splice($targetCardIds, max(0, min($position, count($targetCardIds))), 0, [$card->id]);

            foreach ($targetCardIds as $index => $cardId) {
                Card::whereKey($cardId)->update(
                    ['position' => $index] + ($cardId === $card->id ? ['board_column_id' => $target->id] : [])
                );
            }

            $affectedColumns = [['id' => $target->id, 'card_ids' => $targetCardIds]];

            if ($sourceColumnId !== $target->id) {
                $sourceCardIds = $this->orderedCardIds($sourceColumnId);

                foreach ($sourceCardIds as $index => $cardId) {
                    Card::whereKey($cardId)->update(['position' => $index]);
                }

                $affectedColumns[] = ['id' => $sourceColumnId, 'card_ids' => $sourceCardIds];
            }

            $card->board_column_id = $target->id;

            return $affectedColumns;
        });
    }

    /**
     * @return list<string>
     */
    private function orderedCardIds(string $columnId, ?string $exceptCardId = null): array
    {
        return Card::where('board_column_id', $columnId)
            ->when($exceptCardId, fn ($query) => $query->whereKeyNot($exceptCardId))
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
