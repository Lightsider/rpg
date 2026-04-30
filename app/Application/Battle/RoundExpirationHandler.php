<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;
use App\Application\Contracts\ClockInterface;
use App\Application\Contracts\EventDispatcherInterface;
use App\Events\Battle\BattleEnded;
use App\Events\Battle\BattleUpdated;
use App\Events\Battle\RoundStarted;
use App\Events\Location\BattleRemoved;
use App\Application\Contracts\TransactionInterface;
use App\Services\MapGenerator;

/**
 * Service tasked with detecting and resolving battles where the round time has run out.
 */
class RoundExpirationHandler
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly RoundResolverInterface $roundResolver,
        private readonly MapGenerator $mapGenerator,
        private readonly TransactionInterface $transaction,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly NpcActionService $npcActionService
    ) {
    }

    /**
     * Finds active battles and resolves them if they have expired.
     */
    public function handleExpiredRounds(): void
    {
        $pendingEvents = [];

        $this->transaction->run(function () use (&$pendingEvents) {
            $activeBattles = $this->battleRepository->findActive();

            foreach ($activeBattles as $battle) {
                if ($battle->getState() === BattleState::ACTIVE && $battle->isRoundExpired()) {
                    $this->battleRepository->lockForUpdate($battle->getId());

                    $this->npcActionService->generateNpcActions($battle);

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

            $waitingBattles = $this->battleRepository->findWaiting();
            foreach ($waitingBattles as $battle) {
                $timeout = $battle->getStartTimeoutSeconds();
                if (!$timeout) {
                    continue;
                }

                $expiryTime = $battle->getRoundStartedAt()->modify("+{$timeout} seconds");
                if ($this->clock->now() < $expiryTime) {
                    continue;
                }

                if (!$this->battleRepository->lockForUpdate($battle->getId())) {
                    continue;
                }

                $battle = $this->battleRepository->findById($battle->getId());
                if (!$battle || $battle->getState() !== BattleState::WAITING) {
                    continue;
                }

                $participantCount = count($battle->getParticipants());
                if ($participantCount >= 2) {
                    $battle->startFromLobby();
                    $this->battleRepository->save($battle);
                    $this->mapGenerator->generateForFight($battle);
                    $this->battleRepository->save($battle);

                    $pendingEvents[] = new BattleRemoved($battle->getLocationId(), $battle->getId());
                    $pendingEvents[] = new RoundStarted(
                        battleId: $battle->getId(),
                        round: $battle->getRoundNumber(),
                        timeout: $battle->getRoundDurationSeconds(),
                    );
                } else {
                    $this->battleRepository->delete($battle->getId());
                    $pendingEvents[] = new BattleRemoved($battle->getLocationId(), $battle->getId());
                }
            }
        });

        // Dispatch broadcast events outside the transaction
        foreach ($pendingEvents as $event) {
            $this->eventDispatcher->dispatch($event);
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
