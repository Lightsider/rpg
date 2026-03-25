<?php

declare(strict_types=1);

namespace App\Domain\Equipment;

use App\Domain\Item\Item;

/**
 * Value Object managing a character's 10 equipment slots.
 */
class Equipment
{
    /**
     * @var array<string, Item|null>
     */
    private array $items = [];

    public function __construct()
    {
        // Initialize all slots to null
        foreach (EquipmentSlot::cases() as $slot) {
            $this->items[$slot->value] = null;
        }
    }

    public function getItem(EquipmentSlot $slot): ?Item
    {
        return $this->items[$slot->value];
    }

    public function setItem(EquipmentSlot $slot, ?Item $item): void
    {
        $this->items[$slot->value] = $item;
    }

    /**
     * Retrieves all currently equipped items (excluding empty slots).
     *
     * @return array<string, Item>
     */
    public function getAllEquipped(): array
    {
        return array_filter($this->items, fn(?Item $item) => $item !== null);
    }
}
