<?php

declare(strict_types=1);

namespace App\Domain\Shield;

use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

/**
 * Pure PHP Domain Model for a Shield.
 */
class Shield extends Item
{
    public function __construct(
        int $id,
        string $name,
        private readonly int $blockResistRating = 40,
        private readonly float $pierceDamageReduction = 0.0,
        private readonly int $requiredStrength = 0,
    ) {
        parent::__construct($id, $name, ItemType::SHIELD);
    }

    public function getBlockResistRating(): int
    {
        return $this->blockResistRating;
    }

    public function getPierceDamageReduction(): float
    {
        return $this->pierceDamageReduction;
    }

    public function getRequiredStrength(): int
    {
        return $this->requiredStrength;
    }

    public function getDefensiveAPBonus(): int
    {
        return 1;
    }

    public function getMaxHpMultiplier(): float
    {
        return 0.10;
    }
}
