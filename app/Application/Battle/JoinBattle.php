<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class JoinBattle
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly RoundExpirationHandler $roundExpirationHandler,
        private readonly BattleLobbyService $battleLobbyService
    ) {
    }

    /**
     * @return array{battle:array<string, mixed>}
     */
    public function execute(int $userId, int $battleId): array
    {
        $this->roundExpirationHandler->handleExpiredRounds();

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Fight not found.');
        }

        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $this->battleLobbyService->joinBattle($battle, $character);
    }
}
