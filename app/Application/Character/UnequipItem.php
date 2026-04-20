<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Services\BackpackService;

class UnequipItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackService $backpackService
    ) {
    }

    /**
     * @return array{character:mixed, equipment:array, backpack:array}
     */
    public function execute(int $userId, string $slot): array
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
        $updatedCharacter = $this->characterRepository->findByUserId($userId);

        return [
            'character' => $updatedCharacter,
            'equipment' => $this->backpackService->getEquipmentPayloadByCharacterId($character->getId()),
            'backpack' => $this->backpackService->getBackpackPayloadByCharacterId($character->getId()),
        ];
    }
}
