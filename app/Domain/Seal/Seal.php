<?php

declare(strict_types=1);

namespace App\Domain\Seal;

use App\Domain\Item\Item;
use App\Domain\Item\ItemType;
use App\Domain\Weapon\WeaponArchetype;

use App\Domain\Battle\Rng\RandomGeneratorInterface;

class Seal extends Item
{
    public function __construct(
        int $id,
        string $name,
        private readonly float $minDamage,
        private readonly float $maxDamage,
        private readonly float $flatCritBonus = 0,
        private readonly float $critChanceBonus = 0.0,
        private readonly WeaponArchetype $archetype = WeaponArchetype::UNIVERSAL,
        private readonly int $requiredStrength = 0,
        private readonly int $requiredWit = 0,
    ) {
        parent::__construct($id, $name, ItemType::SEAL);
    }

    public function rollBaseDamage(?RandomGeneratorInterface $rng = null): float
    {
        if ($this->minDamage >= $this->maxDamage) {
            return $this->minDamage;
        }

        if ($rng) {
            return $this->minDamage + $rng->nextFloat() * ($this->maxDamage - $this->minDamage);
        }

        // Maintaining precision for the roll
        $random = mt_rand() / mt_getrandmax();
        return $this->minDamage + $random * ($this->maxDamage - $this->minDamage);
    }

    public function getMinDamage(): float { return $this->minDamage; }
    public function getMaxDamage(): float { return $this->maxDamage; }
    public function getFlatCritBonus(): float { return $this->flatCritBonus; }
    public function getCritChanceBonus(): float { return $this->critChanceBonus; }
    public function getArchetype(): WeaponArchetype { return $this->archetype; }
    public function getRequiredStrength(): int { return $this->requiredStrength; }
    public function getRequiredWit(): int { return $this->requiredWit; }
}
