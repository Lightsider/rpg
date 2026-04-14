<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Character;
use App\Infrastructure\Eloquent\Models\BattleModel;

class ListRecentBattles
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository
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
            
            // We need the location name. Since we used "with(['location'])" in Eloquent,
            // we can fetch it if we had access to the model, but here we have the domain object.
            // Let's refine the domain model or repository to provide the location name.
            // For now, I'll fetch the location name separately or ensure it's in the domain.
            
            // Actually, I'll just fetch the location name from the DB for this list view.
            $locationName = \App\Infrastructure\Eloquent\Models\LocationModel::where('id', $battle->getLocationId())->value('name') ?? 'Unknown';

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
