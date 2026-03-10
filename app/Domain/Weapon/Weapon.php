<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

/**
 * Pure PHP Domain Model for a Weapon.
 */
class Weapon extends Item
{
    public function __construct(
        int $id,
        string $name,
        private readonly int $minDamage,
        private readonly int $maxDamage,
        private readonly DamageType $damageType,
        private readonly float $accuracyBonus,
        private readonly int $blockBreakRating,
        private readonly float $pierceMultiplier,
        private readonly int $maxDamageRating = 0,
    ) {
        parent::__construct($id, $name, ItemType::WEAPON);
    }

    /**
     * Returns a random value between minDamage and maxDamage.
     */
    public function rollBaseDamage(): int
    {
        return mt_rand($this->minDamage, $this->maxDamage);
    }

    public function getMinDamage(): int
    {
        return $this->minDamage;
    }

    public function getMaxDamage(): int
    {
        return $this->maxDamage;
    }

    public function getDamageType(): DamageType
    {
        return $this->damageType;
    }

    public function getAccuracyBonus(): float
    {
        return $this->accuracyBonus;
    }

    /**
     * Raw block-break rating contributed by this weapon.
     * Combined with defender.block_resist_rating in BlockPenetrationService
     * to derive effective penetration probability.
     */
    public function getBlockBreakRating(): int
    {
        return $this->blockBreakRating;
    }

    /**
     * Fraction of base damage dealt on a successful block penetration.
     * e.g. 0.50 → 50% of base damage.
     */
    public function getPierceMultiplier(): float
    {
        return $this->pierceMultiplier;
    }

    public function getMaxDamageRating(): int
    {
        return $this->maxDamageRating;
    }
}
