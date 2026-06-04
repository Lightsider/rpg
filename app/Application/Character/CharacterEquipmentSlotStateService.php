<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Application\Contracts\CharacterStateRepositoryInterface;
use App\Domain\Equipment\EquipmentSlot;

class CharacterEquipmentSlotStateService
{
    public function __construct(
        private readonly CharacterStateRepositoryInterface $characterStateRepository
    ) {
    }

    public function getSealSlotId(int $characterId, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($characterId, $slot);
    }

    public function setSealSlotId(int $characterId, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->characterStateRepository->setEquipmentSlotItemId($characterId, $slot, $itemId);
    }

    public function getArmorSlotId(int $characterId, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($characterId, $slot);
    }

    public function setArmorSlotId(int $characterId, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->characterStateRepository->setEquipmentSlotItemId($characterId, $slot, $itemId);
    }

    public function setArmorValueForSlot(int $characterId, EquipmentSlot $slot, float $value): void
    {
        $this->characterStateRepository->setArmorValueForSlot($characterId, $slot, $value);
    }

    private function getSlotItemId(int $characterId, EquipmentSlot $slot): ?int
    {
        $itemIds = $this->characterStateRepository->getEquipmentItemIds($characterId);
        return $itemIds[$slot->value] ?? null;
    }
}
