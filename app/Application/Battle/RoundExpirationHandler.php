<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;
use App\Events\Battle\BattleEnded;
use App\Events\Battle\BattleUpdated;
use App\Events\Battle\RoundStarted;
use Illuminate\Support\Facades\DB;

/**
 * Service tasked with detecting and resolving battles where the round time has run out.
 */
class RoundExpirationHandler
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly RoundResolverInterface $roundResolver
    ) {
    }

    /**
     * Finds active battles and resolves them if they have expired.
     */
    public function handleExpiredRounds(): void
    {
        $pendingEvents = [];

        DB::transaction(function () use (&$pendingEvents) {
            $activeBattles = $this->battleRepository->findActive();

            foreach ($activeBattles as $battle) {
                if ($battle->getState() === BattleState::ACTIVE && $battle->isRoundExpired()) {
                    $result = $this->roundResolver->resolve($battle);

                    $this->battleLogRepository->saveBatch($battle->getId(), $result->logs);

                    $pendingEvents = array_merge(
                        $pendingEvents,
                        $this->buildPendingEvents($battle, $result->logs)
                    );

                    if ($battle->getState() !== BattleState::FINISHED) {
                        $battle->startNewRound();
                    }

                    $this->battleRepository->save($battle);
                }
            }
        });

        // Dispatch broadcast events outside the transaction
        foreach ($pendingEvents as $event) {
            event($event);
        }
    }

    /**
     * Build the ordered list of events to broadcast after a round resolves.
     *
     * @param BattleLogEntry[] $logs
     * @return object[]
     */
    private function buildPendingEvents(Battle $battle, array $logs): array
    {
        $events = [];

        $events[] = new BattleUpdated($battle, $logs);

        if ($battle->isFinished()) {
            $events[] = new BattleEnded($battle);
        } else {
            $events[] = new RoundStarted(
                battleId: $battle->getId(),
                round: $battle->getRoundNumber(),
                timeout: $battle->getRoundDurationSeconds(),
            );
        }

        return $events;
    }
}
