<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\CharacterModel;

class BackpackSeedService
{
    public function __construct(
        private readonly CharacterLookupService $characterLookup
    ) {
    }

    public function ensureSeeded(CharacterModel $character): void
    {
        if ($character->backpack_seeded) {
            return;
        }

        $character->backpack_seeded = true;
        $character->save();
    }

    public function ensureSeededByUserId(int $userId): void
    {
        $character = $this->characterLookup->findByUserId($userId);
        if (!$character) {
            return;
        }

        $this->ensureSeeded($character);
    }
}

