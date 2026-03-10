<?php

declare(strict_types=1);

namespace App\Domain\Equipment;

use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

class EquipmentService
{
    /**
     * @var array<string, string[]> config('equipment.slots')
     */
    private array $allowedItemTypes;

    public function __construct()
    {
        // For a true domain service, we might pass this via constructor mapping,
        // but since we want to be pragmatic within Laravel, we read config here.
        // We cast to array to satisfy phpstan.
        $this->allowedItemTypes = (array) config('equipment.slots', []);
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
