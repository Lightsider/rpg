<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class CancelBattle
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleLobbyService $battleLobbyService
    ) {
    }

    public function execute(int $userId, int $battleId): void
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $this->battleLobbyService->cancelBattle($battleId, $character);
    }
}
