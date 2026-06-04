<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Application\Character\BackpackReadService;

class UnequipItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly UnequipItemFlow $unequipItemFlow,
        private readonly BackpackReadService $backpackReadService,
        private readonly CharacterPresenter $characterPresenter
    ) {
    }

    /**
     * @return array{character:mixed, equipment:array, backpack:array}
     */
    public function execute(int $userId, string $slot): array
    {
        $characterId = $this->unequipItemFlow->execute($userId, $slot);
        $updatedCharacter = $this->characterRepository->findByUserId($userId);

        return [
            'character' => $updatedCharacter ? $this->characterPresenter->present($updatedCharacter) : null,
            'equipment' => $this->backpackReadService->getEquipmentPayloadByCharacterId($characterId),
            'backpack' => $this->backpackReadService->getBackpackPayloadByCharacterId($characterId),
        ];
    }
}
