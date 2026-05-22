<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Infrastructure\Eloquent\Models\CharacterModel;

class EloquentCharacterModelLookupRepository
{
    public function findById(int $characterId): ?CharacterModel
    {
        return CharacterModel::find($characterId);
    }

    public function findByUserId(int $userId): ?CharacterModel
    {
        return CharacterModel::where('user_id', $userId)->first();
    }
}

