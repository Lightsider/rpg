<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Repositories\CharacterStateReadWriteRepository;

class BackpackSeedService
{
    public function __construct(
        private readonly CharacterStateReadWriteRepository $characterStateRepository
    ) {
    }

    public function ensureSeededByCharacterId(int $characterId): void
    {
        if ($this->characterStateRepository->isBackpackSeeded($characterId)) {
            return;
        }

        $this->characterStateRepository->markBackpackSeeded($characterId);
    }

    public function ensureSeededByUserId(int $userId): void
    {
        $characterId = $this->characterStateRepository->findCharacterIdByUserId($userId);
        if ($characterId === null) {
            return;
        }

        $this->ensureSeededByCharacterId($characterId);
    }
}
