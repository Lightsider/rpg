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
use App\Events\Location\BattleRemoved;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Services\MapGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Service tasked with detecting and resolving battles where the round time has run out.
 */
class RoundExpirationHandler
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly RoundResolverInterface $roundResolver,
        private readonly MapGenerator $mapGenerator
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
                    // Lock the row to avoid double resolution with commit flow.
                    BattleModel::where('id', $battle->getId())->lockForUpdate()->first();

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

            $waitingBattles = BattleModel::where('state', BattleState::WAITING->value)->get();
            foreach ($waitingBattles as $waitingModel) {
                if (!$waitingModel->start_timeout_seconds) {
                    continue;
                }

                $expiryTime = $waitingModel->round_started_at->modify("+{$waitingModel->start_timeout_seconds} seconds");
                if (new \DateTimeImmutable() < $expiryTime) {
                    continue;
                }

                BattleModel::where('id', $waitingModel->id)->lockForUpdate()->first();
                $battle = $this->battleRepository->findById($waitingModel->id);
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
                    $waitingModel->delete();
                    $pendingEvents[] = new BattleRemoved($waitingModel->location_id, $waitingModel->id);
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
