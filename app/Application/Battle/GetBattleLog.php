<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetBattleLog
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly CharacterRepositoryInterface $characterRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $userId, int $battleId): array
    {
        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Fight not found.');
        }

        $character = $this->characterRepository->findByUserId($userId);
        $isParticipant = $character && $battle->getParticipantById($character->getId());

        if (!$isParticipant && !$battle->isFinished()) {
            throw new DomainException('You are not a participant in this ongoing fight.');
        }

        return $this->battleLogRepository->findByBattleId($battleId);
    }
}
