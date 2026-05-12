<?php

namespace App\Domain\Item\Repositories;

interface CharacterItemRepositoryInterface
{
    public function addToBackpack(int $characterId, int $itemId, int $quantity = 1): void;
    public function removeFromBackpack(int $characterId, int $itemId, int $quantity = 1): bool;
    public function getQuantity(int $characterId, int $itemId): int;

    /**
     * @return array<int, array{item_id:int, quantity:int}>
     */
    public function listByCharacterId(int $characterId): array;
}
