<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;

/**
 * Service tasked with detecting and resolving battles where the round time has run out.
 */
class RoundExpirationHandler
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly RoundResolverInterface $roundResolver
    ) {
    }

    /**
     * Finds active battles and resolves them if they have expired.
     */
    public function handleExpiredRounds(): void
    {
        $activeBattles = $this->battleRepository->findActive();

        foreach ($activeBattles as $battle) {
            if ($battle->isRoundExpired() && !$battle->isFinished()) {
                // Resolve all collected actions including default actions for inactive players
                $this->roundResolver->resolve($battle);

                // Prepare for the next round
                $battle->startNewRound();

                // Persist the changes
                $this->battleRepository->save($battle);
            }
        }
    }
}
