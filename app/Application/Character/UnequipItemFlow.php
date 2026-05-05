<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Services\BackpackService;

class UnequipItemFlow
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackService $backpackService,
    ) {
    }

    public function execute(int $userId, string $slot): int
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            throw new DomainException('Cannot edit loadout during an active fight.');
        }

        $this->backpackService->unequipItemByCharacterId($character->getId(), $slot);

        return $character->getId();
    }
}

