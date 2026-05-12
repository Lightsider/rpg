<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Item\Repositories\CharacterItemRepositoryInterface;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;

class EloquentCharacterItemRepository implements CharacterItemRepositoryInterface
{
    public function addToBackpack(int $characterId, int $itemId, int $quantity = 1): void
    {
        $entry = CharacterItemModel::firstOrNew([
            'character_id' => $characterId,
            'item_id' => $itemId,
        ]);

        $entry->quantity = (int) ($entry->quantity ?? 0) + $quantity;
        $entry->save();
    }

    public function removeFromBackpack(int $characterId, int $itemId, int $quantity = 1): bool
    {
        $entry = CharacterItemModel::query()
            ->where('character_id', $characterId)
            ->where('item_id', $itemId)
            ->first();

        if (!$entry || (int) $entry->quantity < $quantity) {
            return false;
        }

        $remaining = (int) $entry->quantity - $quantity;
        if ($remaining <= 0) {
            $entry->delete();
            return true;
        }

        $entry->quantity = $remaining;
        $entry->save();

        return true;
    }

    public function getQuantity(int $characterId, int $itemId): int
    {
        $entry = CharacterItemModel::query()
            ->where('character_id', $characterId)
            ->where('item_id', $itemId)
            ->first();

        return (int) ($entry?->quantity ?? 0);
    }

    public function listByCharacterId(int $characterId): array
    {
        return CharacterItemModel::query()
            ->where('character_id', $characterId)
            ->orderByDesc('id')
            ->get(['item_id', 'quantity'])
            ->map(static fn (CharacterItemModel $entry) => [
                'item_id' => (int) $entry->item_id,
                'quantity' => (int) $entry->quantity,
            ])
            ->all();
    }
}
