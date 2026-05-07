<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class CreateBattle
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly RoundExpirationHandler $roundExpirationHandler,
        private readonly BattleLobbyService $battleLobbyService
    ) {
    }

    public function execute(int $userId, ?int $maxParticipants, ?int $startTimeoutSeconds, bool $fillWithBots = false): int
    {
        $this->roundExpirationHandler->handleExpiredRounds();

        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $this->battleLobbyService->createBattle(
            $character,
            $maxParticipants,
            $startTimeoutSeconds,
            $fillWithBots
        );
    }
}
