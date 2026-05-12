<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Repositories\CharacterItemRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class BackpackReadService
{
    public function __construct(
        private readonly InventoryPayloadAssembler $payloadAssembler,
        private readonly CharacterLookupService $characterLookup,
        private readonly CharacterItemRepositoryInterface $characterItemRepository,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayload(CharacterModel $character): array
    {
        return collect($this->characterItemRepository->listByCharacterId((int) $character->id))
            ->map(function (array $entry) {
                $item = $this->itemRepository->findById((int) $entry['item_id']);
                if (!$item) {
                    return null;
                }

                return $this->payloadAssembler->fromItem($item, (int) $entry['quantity']);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayloadByCharacterId(int $characterId): array
    {
        return $this->getBackpackPayload($this->characterLookup->requireById($characterId));
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayload(CharacterModel $character): array
    {
        return [
            EquipmentSlot::MAIN_HAND->value => $this->itemPayloadById($character->weapon_id),
            EquipmentSlot::OFF_HAND->value => $this->itemPayloadById($character->off_hand_id),
            EquipmentSlot::SEAL_1->value => $this->itemPayloadById($character->seal_1_id),
            EquipmentSlot::SEAL_2->value => $this->itemPayloadById($character->seal_2_id),
            EquipmentSlot::SEAL_3->value => $this->itemPayloadById($character->seal_3_id),
            EquipmentSlot::SEAL_4->value => $this->itemPayloadById($character->seal_4_id),
            EquipmentSlot::HELMET->value => $this->itemPayloadById($character->helmet_id),
            EquipmentSlot::CHEST->value => $this->itemPayloadById($character->chest_id),
            EquipmentSlot::LEGS->value => $this->itemPayloadById($character->legs_id),
            EquipmentSlot::GLOVES->value => $this->itemPayloadById($character->gloves_id),
        ];
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayloadByCharacterId(int $characterId): array
    {
        return $this->getEquipmentPayload($this->characterLookup->requireById($characterId));
    }

    private function itemPayloadById(?int $itemId): ?array
    {
        if (!$itemId) {
            return null;
        }

        $item = $this->itemRepository->findById($itemId);
        return $item ? $this->payloadAssembler->fromItem($item, 1) : null;
    }
}
