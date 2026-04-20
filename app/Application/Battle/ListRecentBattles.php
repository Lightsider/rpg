<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Character;
use App\Domain\Location\Repositories\LocationRepositoryInterface;

class ListRecentBattles
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly LocationRepositoryInterface $locationRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $limit = 15): array
    {
        $battles = $this->battleRepository->findRecent($limit);

        return array_map(function (Battle $battle) {
            $winners = $battle->getVictoryWinners();
            $winnerNames = array_map(fn(Character $c) => $c->getName(), $winners);
            
            $locationName = $this->locationRepository
                ->findById($battle->getLocationId())
                ?->getName() ?? 'Unknown';

            return [
                'id' => $battle->getId(),
                'location_name' => $locationName,
                'participants' => array_map(fn(Character $c) => $c->getName(), array_values($battle->getParticipants())),
                'winner_names' => $winnerNames,
                'status' => $battle->getState()->value,
                'round' => $battle->getRoundNumber(),
                'ended_at' => $battle->jsonSerialize()['round_started_at'] ?? null, // Default
            ];
        }, $battles);
    }
}
