<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

use App\Domain\Battle\Rng\RandomGeneratorInterface;

/**
 * Pure PHP Domain Model for a Weapon.
 */
class Weapon extends Item
{
    public function __construct(
        int $id,
        string $name,
        private readonly float $minDamage,
        private readonly float $maxDamage,
        private readonly DamageType $damageType,
        private readonly float $accuracyBonus = 0.0,
        private readonly int $blockBreakRating = 0,
        private readonly float $pierceMultiplier = 0.0,
        private readonly int $maxDamageRating = 0,
        private readonly WeaponArchetype $archetype = WeaponArchetype::HYBRID,
        private readonly int $requiredStrength = 0,
        private readonly int $requiredWit = 0,
        private readonly float $flatCritBonus = 0,
        private readonly float $critChanceBonus = 0.0,
        private readonly bool $isTwoHanded = false,
    ) {
        parent::__construct($id, $name, ItemType::WEAPON);
    }

    /**
     * Returns a random value between minDamage and maxDamage.
     */
    public function rollBaseDamage(?RandomGeneratorInterface $rng = null): float
    {
        if ($rng) {
            return $this->minDamage + $rng->nextFloat() * ($this->maxDamage - $this->minDamage);
        }
        return (float) mt_rand((int) round($this->minDamage * 100), (int) round($this->maxDamage * 100)) / 100;
    }

    public function getMinDamage(): float
    {
        return $this->minDamage;
    }

    public function getMaxDamage(): float
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

    public function getArchetype(): WeaponArchetype
    {
        return $this->archetype;
    }

    public function getRequiredStrength(): int
    {
        return $this->requiredStrength;
    }

    public function getRequiredWit(): int
    {
        return $this->requiredWit;
    }

    public function getFlatCritBonus(): float
    {
        return (float) $this->flatCritBonus;
    }

    public function getCritChanceBonus(): float
    {
        return $this->critChanceBonus;
    }

    public function isTwoHanded(): bool
    {
        return $this->isTwoHanded;
    }
}
