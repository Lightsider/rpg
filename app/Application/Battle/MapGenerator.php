<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Application\Contracts\TransactionInterface;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Map;
use App\Application\Contracts\MapGenerationStateRepositoryInterface;

class MapGenerator
{
    private const int STARTING_LEFT_X = 0;
    private const int STARTING_RIGHT_OFFSET = 1;
    private const int STARTING_ROW_DIVISOR = 2;
    private const int TEAM_SIDE_PADDING = 1;

    public function __construct(
        private readonly TransactionInterface $transaction,
        private readonly MapGenerationStateRepositoryInterface $mapStateRepository
    ) {
    }

    public function generateForFight(Battle $fight): void
    {
        $this->transaction->run(function () use ($fight) {
            $participants = array_values($fight->getParticipants());
            if (count($participants) === 0) {
                return;
            }

            $targetHeight = Map::DEFAULT_HEIGHT + max(0, count($participants) - 2);

            $mapDimensions = $this->mapStateRepository->ensureMap(
                $fight->getId(),
                Map::DEFAULT_WIDTH,
                $targetHeight
            );

            // Sync the actual map dimensions to the battles table so that
            // Battle::queueAction validates coordinates against the real map size.
            $this->mapStateRepository->syncBattleMapDimensions(
                $fight->getId(),
                $mapDimensions['width'],
                $mapDimensions['height']
            );

            // Map generation creates a rectangular map and assigns starting tiles to current fighters.
            $startY = intdiv($mapDimensions['height'], self::STARTING_ROW_DIVISOR);
            $leftX = self::STARTING_LEFT_X;
            $rightX = $mapDimensions['width'] - self::STARTING_RIGHT_OFFSET;
            $centerX = intdiv($mapDimensions['width'] - 1, 2);

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
                $this->buildTeamPlacements($participantsByTeam['blue'], $leftX, $mapDimensions['height'], $mapDimensions['width'], 1, $usedPositions),
                $this->buildTeamPlacements($participantsByTeam['red'], $rightX, $mapDimensions['height'], $mapDimensions['width'], -1, $usedPositions),
                $this->buildTeamPlacements($participantsByTeam['neutral'], $centerX, $mapDimensions['height'], $mapDimensions['width'], 0, $usedPositions, true)
            );

            // Move ALL participants currently in the DB for this fight to temporary off-map positions first.
            // This prevents unique constraint violations when re-assigning positions.
            $this->mapStateRepository->moveAllPositionsOffMap($fight->getId());

            foreach ($placements as $placement) {
                $this->mapStateRepository->upsertPosition(
                    $fight->getId(),
                    $placement['character_id'],
                    $placement['x'],
                    $placement['y']
                );
                
                // Update the domain object as well
                $participant = $fight->getParticipantById($placement['character_id']);
                if ($participant) {
                    $participant->setPosition($placement['x'], $placement['y']);
                }
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
