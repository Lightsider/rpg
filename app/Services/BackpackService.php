<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\CharacterModel;

class BackpackService
{
    public function __construct(
        private readonly BackpackMutationService $mutationService,
        private readonly BackpackReadService $readService,
        private readonly CharacterLookupService $characterLookup
    ) {
    }

    public function ensureSeeded(CharacterModel $character): void
    {
        if ($character->backpack_seeded) {
            return;
        }

        $character->backpack_seeded = true;
        $character->save();
    }

    public function ensureSeededByUserId(int $userId): void
    {
        $character = $this->characterLookup->findByUserId($userId);
        if (!$character) {
            return;
        }

        $this->ensureSeeded($character);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayload(CharacterModel $character): array
    {
        return $this->readService->getBackpackPayload($character);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayloadByCharacterId(int $characterId): array
    {
        return $this->readService->getBackpackPayloadByCharacterId($characterId);
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayload(CharacterModel $character): array
    {
        return $this->readService->getEquipmentPayload($character);
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayloadByCharacterId(int $characterId): array
    {
        return $this->readService->getEquipmentPayloadByCharacterId($characterId);
    }

    public function equipItem(CharacterModel $character, int $itemId, string $slot): void
    {
        $this->mutationService->equipItem($character, $itemId, $slot);
    }

    public function equipItemByCharacterId(int $characterId, int $itemId, string $slot): void
    {
        $this->mutationService->equipItemByCharacterId($characterId, $itemId, $slot);
    }

    public function unequipItem(CharacterModel $character, string $slot): void
    {
        $this->mutationService->unequipItem($character, $slot);
    }

    public function unequipItemByCharacterId(int $characterId, string $slot): void
    {
        $this->mutationService->unequipItemByCharacterId($characterId, $slot);
    }

    public function validateEquippedItems(CharacterModel $character): array
    {
        return $this->mutationService->validateEquippedItems($character);
    }

    public function validateEquippedItemsByCharacterId(int $characterId): array
    {
        return $this->mutationService->validateEquippedItemsByCharacterId($characterId);
    }

}
