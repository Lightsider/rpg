<?php

declare(strict_types=1);

namespace App\Domain\Armor;

use App\Domain\Item\ItemType;

/**
 * Shield is now a specialized Armor item that can be equipped in the off-hand.
 * It provides active block mechanics but also passive protection/dodge bonuses.
 */
class Shield extends Armor
{
    public function __construct(
        int $id,
        string $name,
        private readonly int $blockResistRating = 40,
        private readonly float $pierceDamageReduction = 0.0,
        private readonly int $requiredStrength = 0,
        float $adArmor = 0.0,
        float $dodgeBonus = 0.0,
    ) {
        parent::__construct(
            id: $id,
            name: $name,
            adArmor: $adArmor,
            dodgeBonus: $dodgeBonus,
            subtype: ArmorSubtype::OFF_HAND, // Placeholder subtype for shields
            requiredStrength: $requiredStrength,
            blockResistRating: $blockResistRating,
            pierceDamageReduction: $pierceDamageReduction
        );
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
