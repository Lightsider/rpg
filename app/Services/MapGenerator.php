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
    private const int TEAM_SIDE_PADDING = 1;

    public function generateForFight(Battle $fight): void
    {
        DB::transaction(function () use ($fight) {
            $participants = array_values($fight->getParticipants());
            if (count($participants) === 0) {
                return;
            }

            $targetHeight = Map::DEFAULT_HEIGHT + max(0, count($participants) - 2);

            $map = FightMapModel::firstOrCreate(
                ['fight_id' => $fight->getId()],
                ['width' => Map::DEFAULT_WIDTH, 'height' => Map::DEFAULT_HEIGHT]
            );

            if ($map->width !== Map::DEFAULT_WIDTH || $map->height !== $targetHeight) {
                $map->width = Map::DEFAULT_WIDTH;
                $map->height = $targetHeight;
                $map->save();
            }

            // Map generation creates a rectangular map and assigns starting tiles to current fighters.
            $startY = intdiv($map->height, self::STARTING_ROW_DIVISOR);
            $leftX = self::STARTING_LEFT_X;
            $rightX = $map->width - self::STARTING_RIGHT_OFFSET;
            $centerX = intdiv($map->width - 1, 2);

            $teams = $fight->getParticipantTeams();
            $participantsByTeam = [
                'blue' => [],
                'red' => [],
                'neutral' => [],
            ];

            foreach ($participants as $participant) {
                $team = $teams[$participant->getId()] ?? 'neutral';
                $participantsByTeam[$team][] = $participant;
            }

            $usedPositions = [];
            $placements = array_merge(
                $this->buildTeamPlacements($participantsByTeam['blue'], $leftX, $map->height, $map->width, 1, $usedPositions),
                $this->buildTeamPlacements($participantsByTeam['red'], $rightX, $map->height, $map->width, -1, $usedPositions),
                $this->buildTeamPlacements($participantsByTeam['neutral'], $centerX, $map->height, $map->width, 0, $usedPositions, true)
            );

            foreach ($placements as $placement) {
                FighterPositionModel::updateOrCreate(
                    ['fight_id' => $fight->getId(), 'character_id' => $placement['character_id']],
                    ['x' => $placement['x'], 'y' => $placement['y']]
                );
            }
        });
    }

    /**
     * @param array<int, \App\Domain\Character\Character> $participants
     * @return array<int, array{character_id:int, x:int, y:int}>
     */
    private function buildTeamPlacements(
        array $participants,
        int $startX,
        int $height,
        int $width,
        int $direction,
        array &$usedPositions,
        bool $centered = false
    ): array
    {
        if (count($participants) === 0) {
            return [];
        }

        $placements = [];
        $rows = $this->buildRowOrder($height, $centered);
        $xOrder = $this->buildColumnOrder($startX, $width, $direction, $centered);

        foreach ($participants as $participant) {
            $placed = false;

            foreach ($xOrder as $x) {
                foreach ($rows as $y) {
                    $key = $x . ':' . $y;
                    if (isset($usedPositions[$key])) {
                        continue;
                    }

                    $usedPositions[$key] = true;
                    $placements[] = [
                        'character_id' => $participant->getId(),
                        'x' => $x,
                        'y' => $y,
                    ];
                    $placed = true;
                    break 2;
                }
            }

            if (!$placed) {
                $fallbackKey = $startX . ':0';
                if (!isset($usedPositions[$fallbackKey])) {
                    $usedPositions[$fallbackKey] = true;
                }

                $placements[] = [
                    'character_id' => $participant->getId(),
                    'x' => $startX,
                    'y' => 0,
                ];
            }
        }

        return $placements;
    }

    /**
     * @return int[]
     */
    private function buildRowOrder(int $height, bool $centered): array
    {
        $min = self::TEAM_SIDE_PADDING;
        $max = max($min, $height - 1 - self::TEAM_SIDE_PADDING);
        $rows = range($min, $max);
        if ($rows === []) {
            $rows = range(0, max(0, $height - 1));
        }

        if (!$centered) {
            return $rows;
        }

        $center = intdiv($height, 2);
        usort($rows, function (int $a, int $b) use ($center) {
            $da = abs($a - $center);
            $db = abs($b - $center);
            if ($da === $db) {
                return $a <=> $b;
            }
            return $da <=> $db;
        });

        return $rows;
    }

    /**
     * @return int[]
     */
    private function buildColumnOrder(int $startX, int $width, int $direction, bool $centered): array
    {
        if ($width <= 0) {
            return [$startX];
        }

        $startX = max(0, min($width - 1, $startX));

        if ($centered) {
            $order = [$startX];
            for ($offset = 1; $offset < $width; $offset++) {
                $left = $startX - $offset;
                $right = $startX + $offset;
                if ($left >= 0) {
                    $order[] = $left;
                }
                if ($right < $width) {
                    $order[] = $right;
                }
            }
            return $order;
        }

        if ($direction >= 0) {
            return range($startX, $width - 1);
        }

        return range($startX, 0);
    }
}
