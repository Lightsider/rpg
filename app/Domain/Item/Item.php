<?php

declare(strict_types=1);

namespace App\Domain\Item;

/**
 * Base domain model for all equipable items.
 */
abstract class Item
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly ItemType $itemType,
        private readonly int $requiredLevel = 1,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getItemType(): ItemType
    {
        return $this->itemType;
    }

    public function getRequiredLevel(): int
    {
        return $this->requiredLevel;
    }

    /**
     * Foundation: Base block-resistant rating provided by the item.
     */
    public function getBlockResistRating(): int
    {
        return 0;
    }

    /**
     * Foundation: Fractional pierce damage reduction (e.g., 0.10 for 10% reduction).
     */
    public function getPierceDamageReduction(): float
    {
        return 0.0;
    }
}
