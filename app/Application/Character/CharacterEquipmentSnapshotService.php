<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Application\Contracts\CharacterStateRepositoryInterface;
use App\Domain\Armor\Armor;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Item;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;

class CharacterEquipmentSnapshotService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterStateRepositoryInterface $characterStateRepository
    ) {
    }

    public function buildCharacterDomain(int $characterId): \App\Domain\Character\Character
    {
        $snapshot = $this->characterStateRepository->getCharacterEquipmentSnapshot($characterId);
        if ($snapshot === null) {
            throw new DomainException('Character not found.');
        }

        $equipment = new Equipment();
        $itemIds = $snapshot['equipment'];

        $mainHand = $this->findItem($itemIds[EquipmentSlot::MAIN_HAND->value]);
        if ($mainHand instanceof Weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $mainHand);
        }

        $offHand = $this->findItem($itemIds[EquipmentSlot::OFF_HAND->value]);
        if ($offHand instanceof Armor || $offHand instanceof Weapon) {
            $equipment->setItem(EquipmentSlot::OFF_HAND, $offHand);
        }

        foreach ([1, 2, 3, 4] as $i) {
            $slot = constant("App\\Domain\\Equipment\\EquipmentSlot::SEAL_{$i}");
            $item = $this->findItem($itemIds[$slot->value]);
            if ($item instanceof Seal) {
                $equipment->setItem($slot, $item);
            }
        }

        foreach ([EquipmentSlot::HELMET, EquipmentSlot::CHEST, EquipmentSlot::LEGS, EquipmentSlot::GLOVES] as $slot) {
            $item = $this->findItem($itemIds[$slot->value]);
            if ($item instanceof Armor) {
                $equipment->setItem($slot, $item);
            }
        }

        return new \App\Domain\Character\Character(
            id: $snapshot['id'],
            userId: $snapshot['user_id'],
            name: $snapshot['name'],
            strength: $snapshot['strength'],
            agility: $snapshot['dexterity'],
            constitution: $snapshot['constitution'],
            wit: $snapshot['wit'],
            maxHp: $snapshot['max_hp'],
            currentHp: $snapshot['hp'],
            equipment: $equipment,
            locationId: $snapshot['location_id'],
            level: $snapshot['level'],
        );
    }

    public function getMainHandWeapon(int $characterId): ?Weapon
    {
        $itemIds = $this->characterStateRepository->getEquipmentItemIds($characterId);
        $item = $this->findItem($itemIds[EquipmentSlot::MAIN_HAND->value]);

        return $item instanceof Weapon ? $item : null;
    }

    /**
     * @return array<int, Item>
     */
    public function getEquippedItems(int $characterId): array
    {
        $ids = array_filter(
            $this->characterStateRepository->getEquipmentItemIds($characterId),
            static fn (?int $id) => $id !== null
        );

        $items = [];
        foreach (array_unique($ids) as $id) {
            $item = $this->findItem($id);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function findItem(?int $itemId): ?Item
    {
        return $itemId === null ? null : $this->itemRepository->findById($itemId);
    }
}
