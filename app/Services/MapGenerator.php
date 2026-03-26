<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Map;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use Illuminate\Support\Facades\DB;

class MapGenerator
{
    private const int STARTING_LEFT_X = 0;
    private const int STARTING_RIGHT_OFFSET = 1;
    private const int STARTING_ROW_DIVISOR = 2;
    private const int MAX_START_POSITIONS = 2;

    public function generateForFight(Battle $fight): void
    {
        DB::transaction(function () use ($fight) {
            $map = FightMapModel::firstOrCreate(
                ['fight_id' => $fight->getId()],
                ['width' => Map::DEFAULT_WIDTH, 'height' => Map::DEFAULT_HEIGHT]
            );

            if ($map->width !== Map::DEFAULT_WIDTH || $map->height !== Map::DEFAULT_HEIGHT) {
                $map->width = Map::DEFAULT_WIDTH;
                $map->height = Map::DEFAULT_HEIGHT;
                $map->save();
            }

            // Map generation creates a default rectangular map and assigns starting tiles to current fighters.
            $participants = array_values($fight->getParticipants());
            if (count($participants) === 0) {
                return;
            }

            $startY = intdiv($map->height, self::STARTING_ROW_DIVISOR);
            $positions = [
                ['x' => self::STARTING_LEFT_X, 'y' => $startY],
                ['x' => $map->width - self::STARTING_RIGHT_OFFSET, 'y' => $startY],
            ];

            $count = min(count($participants), self::MAX_START_POSITIONS);
            for ($i = 0; $i < $count; $i++) {
                $characterId = $participants[$i]->getId();

                FighterPositionModel::firstOrCreate(
                    ['fight_id' => $fight->getId(), 'character_id' => $characterId],
                    ['x' => $positions[$i]['x'], 'y' => $positions[$i]['y']]
                );
            }
        });
    }
}
