<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Services\BackpackService;

class EquipItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly EquipItemFlow $equipItemFlow,
        private readonly BackpackService $backpackService,
        private readonly CharacterPresenter $characterPresenter
    ) {
    }

    /**
     * @return array{character:mixed, equipment:array, backpack:array}
     */
    public function execute(int $userId, int $itemId, string $slot): array
    {
        $characterId = $this->equipItemFlow->execute($userId, $itemId, $slot);
        $updatedCharacter = $this->characterRepository->findByUserId($userId);

        return [
            'character' => $updatedCharacter ? $this->characterPresenter->present($updatedCharacter) : null,
            'equipment' => $this->backpackService->getEquipmentPayloadByCharacterId($characterId),
            'backpack' => $this->backpackService->getBackpackPayloadByCharacterId($characterId),
        ];
    }
}
