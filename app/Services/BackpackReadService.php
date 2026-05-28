<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Contracts\CharacterStateRepositoryInterface;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Repositories\CharacterItemRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;

class BackpackReadService
{
    public function __construct(
        private readonly InventoryPayloadAssembler $payloadAssembler,
        private readonly CharacterItemRepositoryInterface $characterItemRepository,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterStateRepositoryInterface $characterStateRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayloadByCharacterId(int $characterId): array
    {
        return collect($this->characterItemRepository->listByCharacterId($characterId))
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
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayloadByCharacterId(int $characterId): array
    {
        $itemIds = $this->characterStateRepository->getEquipmentItemIds($characterId);

        return [
            EquipmentSlot::MAIN_HAND->value => $this->itemPayloadById($itemIds[EquipmentSlot::MAIN_HAND->value]),
            EquipmentSlot::OFF_HAND->value => $this->itemPayloadById($itemIds[EquipmentSlot::OFF_HAND->value]),
            EquipmentSlot::SEAL_1->value => $this->itemPayloadById($itemIds[EquipmentSlot::SEAL_1->value]),
            EquipmentSlot::SEAL_2->value => $this->itemPayloadById($itemIds[EquipmentSlot::SEAL_2->value]),
            EquipmentSlot::SEAL_3->value => $this->itemPayloadById($itemIds[EquipmentSlot::SEAL_3->value]),
            EquipmentSlot::SEAL_4->value => $this->itemPayloadById($itemIds[EquipmentSlot::SEAL_4->value]),
            EquipmentSlot::HELMET->value => $this->itemPayloadById($itemIds[EquipmentSlot::HELMET->value]),
            EquipmentSlot::CHEST->value => $this->itemPayloadById($itemIds[EquipmentSlot::CHEST->value]),
            EquipmentSlot::LEGS->value => $this->itemPayloadById($itemIds[EquipmentSlot::LEGS->value]),
            EquipmentSlot::GLOVES->value => $this->itemPayloadById($itemIds[EquipmentSlot::GLOVES->value]),
        ];
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
