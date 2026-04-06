<?php

declare(strict_types=1);

namespace App\Domain\Armor;

use App\Domain\Item\Item;
use App\Domain\Item\ItemType;

class Armor extends Item
{
    public function __construct(
        int $id,
        string $name,
        private readonly float $adArmor,
        private readonly float $dodgeBonus,
        private readonly ArmorSubtype $subtype,
        private readonly int $requiredStrength = 0,
        private readonly int $requiredWit = 0,
        private readonly int $requiredDexterity = 0,
        private readonly int $requiredConstitution = 0,
        private readonly int $blockResistRating = 0,
        private readonly float $pierceDamageReduction = 0.0,
    ) {
        parent::__construct($id, $name, ItemType::ARMOR);
    }

    public function getAdArmor(): float
    {
        return $this->adArmor;
    }

    public function getDodgeBonus(): float
    {
        return $this->dodgeBonus;
    }

    public function getSubtype(): ArmorSubtype
    {
        return $this->subtype;
    }

    public function getRequiredStrength(): int
    {
        return $this->requiredStrength;
    }

    public function getRequiredWit(): int
    {
        return $this->requiredWit;
    }

    public function getRequiredDexterity(): int
    {
        return $this->requiredDexterity;
    }

    public function getRequiredConstitution(): int
    {
        return $this->requiredConstitution;
    }

    public function getBlockResistRating(): int
    {
        return $this->blockResistRating;
    }

    public function getPierceDamageReduction(): float
    {
        return $this->pierceDamageReduction;
    }
}
