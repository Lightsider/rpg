<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolverInterface;
use App\Application\Contracts\EventDispatcherInterface;
use App\Domain\DomainException;
use App\Events\Battle\BattleEnded;
use App\Events\Battle\BattleUpdated;
use App\Events\Battle\RoundStarted;
use App\Events\Battle\BattleCommitted;
use App\Application\Contracts\TransactionInterface;

/**
 * Application service to commit a character's actions and potentially resolve the round.
 */
class CommitRoundAction
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly RoundResolverInterface $roundResolver,
        private readonly TransactionInterface $transaction,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NpcActionService $npcActionService,
    ) {
    }

    /**
     * Executes the commit round (commitment) logic.
     *
     * @throws DomainException If battle not found or round expired.
     */
    public function execute(int $battleId, int $characterId): void
    {
        // Capture broadcast data after the transaction so events go out
        // only once the DB is consistent (avoids broadcasting stale data).
        $pendingEvents = [];

        $this->transaction->run(function () use ($battleId, $characterId, &$pendingEvents) {
            if (!$this->battleRepository->lockForUpdate($battleId)) {
                throw new DomainException('Battle not found.');
            }

            // 1. Load battle
            $battle = $this->battleRepository->findById($battleId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }

            // 2. Validate state (Must be ACTIVE for commitments)
            if ($battle->getState() !== BattleState::ACTIVE) {
                throw new DomainException('Battle is not in ACTIVE state for commitments.');
            }

            // 3. Ensure round not expired
            if ($battle->isRoundExpired()) {
                throw new DomainException('Round has expired.');
            }

            // 4. If already committed → do nothing (Idempotency)
            if ($battle->isCharacterCommitted($characterId)) {
                return;
            }

            // 5. Mark character as committed
            $battle->commitCharacter($characterId);

            // 6. Auto-generate NPC actions (if any NPCs haven't committed yet)
            $this->npcActionService->generateNpcActions($battle);

            // 7. If ALL alive participants have committed → resolve
            if ($battle->areAllCommitted()) {
                // Double safety against race conditions in transactions
                if ($battle->getState() === BattleState::ACTIVE) {
                    $result = $this->roundResolver->resolve($battle);

                    $this->battleLogRepository->saveBatch($battle->getId(), $result->logs);

                    $pendingEvents = $this->buildPendingEvents($battle, $result->logs);

                    if ($battle->getState() !== BattleState::FINISHED) {
                        $battle->startNewRound();
                    }
                }
            } else {
                // Individual commitment broadcast
                $pendingEvents = [new BattleCommitted($battle, $characterId)];
            }

            // 8. Save battle
            $this->battleRepository->save($battle);
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

        // 1. Round result (always sent)
        $events[] = new BattleUpdated($battle, $logs);

        if ($battle->isFinished()) {
            // 2a. Battle over
            $events[] = new BattleEnded($battle);
        } else {
            // 2b. New round started
            $events[] = new RoundStarted(
                battleId: $battle->getId(),
                round: $battle->getRoundNumber(),
                timeout: $battle->getRoundDurationSeconds(),
            );
        }

        return $events;
    }
}
