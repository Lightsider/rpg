<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Character\Character;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Events\Battle\BattleJoined;
use App\Events\Battle\RoundStarted;
use App\Events\Location\BattleCreated;
use App\Events\Location\BattleRemoved;
use App\Services\MapGenerator;

class BattleLobbyService
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly LeaveWaitingBattleAction $leaveWaitingBattleAction,
        private readonly MapGenerator $mapGenerator
    ) {
    }

    public function createBattle(Character $character): int
    {
        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            throw new DomainException('Character is already in another fight.');
        }

        $battle = new Battle(
            id: 0,
            locationId: $character->getLocationId(),
            participants: [$character->getId() => $character],
            map: Map::default(),
            state: BattleState::WAITING
        );

        $battleId = $this->battleRepository->save($battle);

        $battle = $this->battleRepository->findById($battleId);
        if ($battle) {
            $this->mapGenerator->generateForFight($battle);
            event(new BattleCreated($battle->jsonSerialize()));
        }

        return $battleId;
    }

    /**
     * @return array{battle: Battle, timer_remaining: int}
     */
    public function joinBattle(Battle $battle, Character $character): array
    {
        if ($battle->getState() !== BattleState::WAITING) {
            throw new DomainException('Fight is no longer joinable.');
        }

        if (count($battle->getParticipants()) >= 2) {
            throw new DomainException('Fight is full.');
        }

        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            throw new DomainException('Character is already in another fight.');
        }

        $battle->addParticipant($character);
        $this->battleRepository->save($battle);

        $battle = $this->battleRepository->findById($battle->getId());
        if ($battle) {
            $this->mapGenerator->generateForFight($battle);
            $battle = $this->battleRepository->findById($battle->getId());
        }

        if ($battle) {
            $timerRemaining = $this->calculateTimerRemaining($battle);
            event(new BattleJoined($battle, $timerRemaining));

            if ($battle->getState() === BattleState::ACTIVE) {
                event(new BattleRemoved($battle->getLocationId(), $battle->getId()));
                event(new RoundStarted(
                    battleId: $battle->getId(),
                    round: $battle->getRoundNumber(),
                    timeout: $battle->getRoundDurationSeconds(),
                ));
            }
        }

        return [
            'battle' => $battle,
            'timer_remaining' => $battle ? $this->calculateTimerRemaining($battle) : 0,
        ];
    }

    public function cancelBattle(int $battleId, Character $character): void
    {
        $this->leaveWaitingBattleAction->execute($battleId, $character->getId());
        event(new BattleRemoved($character->getLocationId(), $battleId));
    }

    private function calculateTimerRemaining(Battle $battle): int
    {
        $now = new \DateTimeImmutable();
        $expiryTime = $battle->getRoundStartedAt()->modify("+{$battle->getRoundDurationSeconds()} seconds");
        return max(0, $expiryTime->getTimestamp() - $now->getTimestamp());
    }
}
