<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

/**
 * Pure PHP Domain Model for a Weapon.
 */
class Weapon
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly int $minDamage,
        private readonly int $maxDamage,
        private readonly DamageType $damageType,
        private readonly float $accuracyBonus,
        private readonly float $blockBreakChance
    ) {
    }

    /**
     * Returns a random value between minDamage and maxDamage.
     */
    public function rollBaseDamage(): int
    {
        return mt_rand($this->minDamage, $this->maxDamage);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function getBlockBreakChance(): float
    {
        return $this->blockBreakChance;
    }
}
