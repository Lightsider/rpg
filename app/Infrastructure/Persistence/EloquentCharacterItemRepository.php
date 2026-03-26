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
}
