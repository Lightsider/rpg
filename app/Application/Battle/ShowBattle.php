<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class ShowBattle
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly RoundExpirationHandler $roundExpirationHandler,
        private readonly BattleViewFactory $battleViewFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, int $battleId): array
    {
        $this->roundExpirationHandler->handleExpiredRounds();

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Fight not found.');
        }

        $character = $this->characterRepository->findByUserId($userId);
        $isParticipant = $character && $battle->getParticipantById($character->getId());

        if (!$isParticipant && !$battle->isFinished()) {
            throw new DomainException('You are not a participant in this ongoing fight.');
        }

        $timerRemaining = $this->calculateTimerRemaining($battle->getRoundStartedAt(), $battle->getRoundDurationSeconds());
        $view = $this->battleViewFactory->build($battle);
        $view['timer_remaining'] = $timerRemaining;

        return $view;
    }

    private function calculateTimerRemaining(\DateTimeImmutable $roundStartedAt, int $durationSeconds): int
    {
        $now = new \DateTimeImmutable();
        $expiryTime = $roundStartedAt->modify("+{$durationSeconds} seconds");
        return max(0, $expiryTime->getTimestamp() - $now->getTimestamp());
    }
}
