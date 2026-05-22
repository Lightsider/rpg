<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Repositories\EloquentCharacterModelLookupRepository;

class CharacterLookupService
{
    public function __construct(
        private readonly EloquentCharacterModelLookupRepository $lookupRepository
    ) {
    }

    public function requireById(int $characterId): CharacterModel
    {
        $character = $this->lookupRepository->findById($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $character;
    }

    public function findByUserId(int $userId): ?CharacterModel
    {
        return $this->lookupRepository->findByUserId($userId);
    }
}
