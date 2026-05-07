<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Character\Character;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Application\Contracts\ClockInterface;
use App\Application\Contracts\EventDispatcherInterface;
use App\Domain\DomainException;
use App\Events\Battle\BattleJoined;
use App\Events\Battle\RoundStarted;
use App\Events\Location\BattleCreated;
use App\Events\Location\BattleRemoved;
use App\Services\MapGenerator;
use App\Services\TeamAssigner;

class BattleLobbyService
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly LeaveWaitingBattleAction $leaveWaitingBattleAction,
        private readonly MapGenerator $mapGenerator,
        private readonly \App\Domain\Location\Repositories\LocationRepositoryInterface $locationRepository,
        private readonly TeamAssigner $teamAssigner,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly BattleSummaryPresenter $battleSummaryPresenter,
        private readonly BotFillingService $botFillingService
    ) {
    }

    public function createBattle(
        Character $character,
        ?int $maxParticipants = null,
        ?int $startTimeoutSeconds = null,
        bool $fillWithBots = false
    ): int
    {
        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            throw new DomainException('Character is already in another fight.');
        }

        $location = $this->locationRepository->findById($character->getLocationId());
        $maxParticipants = $maxParticipants ?? $location?->getMaxPlayers();
        $startTimeoutSeconds = $startTimeoutSeconds ?? $location?->getStartTimeoutSeconds();

        $battle = new Battle(
            id: 0,
            locationId: $character->getLocationId(),
            participants: [$character->getId() => $character],
            map: Map::default(),
            maxParticipants: $maxParticipants,
            startTimeoutSeconds: $startTimeoutSeconds,
            state: BattleState::WAITING,
            fillWithBots: $fillWithBots
        );
        $battle->assignTeam($character->getId(), $this->teamAssigner->assign($battle->getParticipantTeams()));

        $battleId = $this->battleRepository->save($battle);

        $battle = $this->battleRepository->findById($battleId);
        if ($battle) {
            $this->mapGenerator->generateForFight($battle);
            $this->eventDispatcher->dispatch(new BattleCreated($this->battleSummaryPresenter->present($battle)));
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

        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            throw new DomainException('Character is already in another fight.');
        }

        $battle->addParticipant($character);
        $battle->assignTeam($character->getId(), $this->teamAssigner->assign($battle->getParticipantTeams()));
        $this->battleRepository->save($battle);

        $startResult = $this->tryStartWaitingBattle($battle);
        if ($startResult === 'cancelled') {
            throw new DomainException('Fight expired.');
        }

        $battle = $this->battleRepository->findById($battle->getId());
        if ($battle) {
            $this->mapGenerator->generateForFight($battle);
            $battle = $this->battleRepository->findById($battle->getId());
        }

        if ($battle) {
            $timerRemaining = $this->calculateTimerRemaining($battle);
            $this->eventDispatcher->dispatch(new BattleJoined($battle, $timerRemaining));

            if ($battle->getState() === BattleState::ACTIVE) {
                $this->eventDispatcher->dispatch(new BattleRemoved($battle->getLocationId(), $battle->getId()));
                $this->eventDispatcher->dispatch(new RoundStarted(
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
        $this->eventDispatcher->dispatch(new BattleRemoved($character->getLocationId(), $battleId));
    }

    private function calculateTimerRemaining(Battle $battle): int
    {
        $now = $this->clock->now();
        $timeout = $battle->getStartTimeoutSeconds() ?? $battle->getRoundDurationSeconds();
        $expiryTime = $battle->getRoundStartedAt()->modify("+{$timeout} seconds");
        return max(0, $expiryTime->getTimestamp() - $now->getTimestamp());
    }

    private function tryStartWaitingBattle(Battle $battle): ?string
    {
        if ($battle->getState() !== BattleState::WAITING) {
            return null;
        }

        $max = $battle->getMaxParticipants();
        $count = count($battle->getParticipants());

        $now = $this->clock->now();
        $timeout = $battle->getStartTimeoutSeconds();
        $expired = $timeout !== null
            ? $now >= $battle->getRoundStartedAt()->modify("+{$timeout} seconds")
            : false;

        if ($max !== null && $count >= $max) {
            $battle->startFromLobby();
            $this->battleRepository->save($battle);
            return 'started';
        }

        if ($expired) {
            if ($count >= 2 || $battle->shouldFillWithBots()) {
                if ($battle->shouldFillWithBots()) {
                    $this->botFillingService->fillBattle($battle);
                }
                $battle->startFromLobby();
                $this->battleRepository->save($battle);
                return 'started';
            } else {
                return 'cancelled';
            }
        }

        return null;
    }
}
