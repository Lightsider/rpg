<?php

declare(strict_types=1);

namespace App\Domain\Equipment;

use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;
use App\Domain\Weapon\Weapon;

class EquipmentService
{
    /**
     * @param array<string, string[]> $allowedItemTypes
     */
    public function __construct(
        private readonly array $allowedItemTypes
    ) {
    }

    /**
     * Checks if the given Item can be placed in the given EquipmentSlot.
     */
    public function isItemAllowedInSlot(Item $item, EquipmentSlot $slot): bool
    {
        $allowedTypeStrings = $this->allowedItemTypes[$slot->value] ?? [];

        // Convert the item's enum type to its string value
        return in_array($item->getItemType()->value, $allowedTypeStrings, true);
    }

    /**
     * Equips an item to a character's slot.
     * Throws an exception if the item type is incompatible with the slot.
     */
    public function equipItem(Character $character, Item $item, EquipmentSlot $slot): void
    {
        if (!$this->isItemAllowedInSlot($item, $slot)) {
            throw new DomainException(
                sprintf(
                    "Cannot equip item type '%s' into slot '%s'.",
                    $item->getItemType()->value,
                    $slot->value
                )
            );
        }

        // Special handling for 2-handed weapons
        if ($slot === EquipmentSlot::MAIN_HAND && $item instanceof Weapon && $item->isTwoHanded()) {
            if ($character->getEquipment()->getItem(EquipmentSlot::OFF_HAND) !== null) {
                throw new DomainException("Offhand must be empty to equip a 2-handed weapon.");
            }
            if ($character->getEquipment()->getItem(EquipmentSlot::MAIN_HAND) !== null) {
                throw new DomainException("Main hand must be empty to equip a 2-handed weapon.");
            }
        }

        if ($slot === EquipmentSlot::OFF_HAND) {
            $mainHandItem = $character->getEquipment()->getItem(EquipmentSlot::MAIN_HAND);
            if ($mainHandItem instanceof Weapon && $mainHandItem->isTwoHanded()) {
                throw new DomainException("Cannot equip offhand item when a 2-handed weapon is equipped.");
            }
        }

        $character->getEquipment()->setItem($slot, $item);
    }

    /**
     * Removes whatever item is currently in the specified slot.
     */
    public function unequipItem(Character $character, EquipmentSlot $slot): void
    {
        $character->getEquipment()->setItem($slot, null);
    }

    /**
     * Retrieves all items currently equipped by the character.
     *
     * @return array<string, Item>
     */
    public function getEquippedItems(Character $character): array
    {
        return $character->getEquipment()->getAllEquipped();
    }
}
