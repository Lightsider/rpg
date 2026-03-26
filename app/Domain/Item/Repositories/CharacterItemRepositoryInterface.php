<?php

namespace App\Domain\Item\Repositories;

interface CharacterItemRepositoryInterface
{
    public function addToBackpack(int $characterId, int $itemId): void;
}
