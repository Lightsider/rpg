<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

use App\Domain\Item\ItemType;
use App\Domain\Battle\Rng\RandomGeneratorInterface;

/**
 * Specialized off-hand weapon that provides Parry utility and +1 Special AP.
 */
class Dagger extends Weapon
{
    public function __construct(
        int $id,
        string $name,
        float $minDamage,
        float $maxDamage,
        DamageType $damageType = DamageType::SLASHING,
        int $blockBreakRating = 20,
        private readonly int $parryRating = 5,
    ) {
        parent::__construct(
            id: $id,
            name: $name,
            minDamage: $minDamage,
            maxDamage: $maxDamage,
            damageType: $damageType,
            accuracyBonus: 0.0,
            blockBreakRating: $blockBreakRating,
            pierceMultiplier: 0.0,
            maxDamageRating: 0, // No max damage bonus
            itemType: ItemType::OFFHAND_WEAPON
        );
    }

    public function getParryRating(): int
    {
        return $this->parryRating;
    }

    public function getOffHandAPBonus(): int
    {
        return 1;
    }

    /**
     * Daggers don't trigger max damage procs.
     */
    public function isMaxDamageEnabled(): bool
    {
        return false;
    }
}
