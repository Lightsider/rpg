<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;

class MapGenerationStateRepository
{
    /**
     * @return array{width:int, height:int}
     */
    public function ensureMap(int $fightId, int $width, int $height): array
    {
        $map = FightMapModel::firstOrCreate(
            ['fight_id' => $fightId],
            ['width' => $width, 'height' => $height]
        );

        if ((int) $map->width !== $width || (int) $map->height !== $height) {
            $map->width = $width;
            $map->height = $height;
            $map->save();
        }

        return [
            'width' => (int) $map->width,
            'height' => (int) $map->height,
        ];
    }

    public function syncBattleMapDimensions(int $fightId, int $width, int $height): void
    {
        BattleModel::where('id', $fightId)->update([
            'map_width' => $width,
            'map_height' => $height,
        ]);
    }

    public function moveAllPositionsOffMap(int $fightId): void
    {
        FighterPositionModel::where('fight_id', $fightId)->each(function (FighterPositionModel $pos) {
            $pos->update(['x' => -1, 'y' => -1 - $pos->character_id]);
        });
    }

    public function upsertPosition(int $fightId, int $characterId, int $x, int $y): void
    {
        FighterPositionModel::updateOrCreate(
            ['fight_id' => $fightId, 'character_id' => $characterId],
            ['x' => $x, 'y' => $y]
        );
    }
}
