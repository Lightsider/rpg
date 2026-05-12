<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterLookupService
{
    public function requireById(int $characterId): CharacterModel
    {
        $character = CharacterModel::find($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $character;
    }

    public function findByUserId(int $userId): ?CharacterModel
    {
        return CharacterModel::where('user_id', $userId)->first();
    }
}

