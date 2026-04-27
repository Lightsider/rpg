<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Application\Contracts\ClockInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class ListBattles
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly RoundExpirationHandler $roundExpirationHandler,
        private readonly ClockInterface $clock,
        private readonly BattleSummaryPresenter $battleSummaryPresenter
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        $this->roundExpirationHandler->handleExpiredRounds();

        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $fights = $this->battleRepository->findJoinableByLocation($character->getLocationId());

        return array_map(function ($fight) {
            $timeout = $fight->getStartTimeoutSeconds();
            $expiresAt = $timeout !== null
                ? $fight->getRoundStartedAt()->modify("+{$timeout} seconds")
                : null;
            $timerRemaining = $expiresAt ? max(0, $expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) : null;

            return array_merge($this->battleSummaryPresenter->present($fight), [
                'timer_remaining' => $timerRemaining,
            ]);
        }, $fights);
    }
}
