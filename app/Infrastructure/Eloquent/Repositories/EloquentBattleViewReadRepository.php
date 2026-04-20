<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Battle\Repositories\BattleViewReadRepositoryInterface;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;

class EloquentBattleViewReadRepository implements BattleViewReadRepositoryInterface
{
    public function getMapSnapshot(int $battleId, int $fallbackWidth, int $fallbackHeight): array
    {
        $mapModel = FightMapModel::where('fight_id', $battleId)->first();
        $width = (int) ($mapModel?->width ?? $fallbackWidth);
        $height = (int) ($mapModel?->height ?? $fallbackHeight);

        $positions = FighterPositionModel::where('fight_id', $battleId)
            ->get()
            ->map(static fn (FighterPositionModel $pos): array => [
                'character_id' => (int) $pos->character_id,
                'x' => (int) $pos->x,
                'y' => (int) $pos->y,
            ])
            ->values()
            ->all();

        return [
            'width' => $width,
            'height' => $height,
            'positions' => $positions,
        ];
    }
}

