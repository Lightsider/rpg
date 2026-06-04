<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Armor\Armor;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;

class InventoryPayloadAssembler
{
    /**
     * @return array<string, mixed>
     */
    public function fromItem(Item $item, int $quantity): array
    {
        $type = $item->getItemType()->value;
        $minDamage = null;
        $maxDamage = null;
        $damageType = null;
        $accuracyBonus = null;
        $blockBreakRating = null;
        $pierceMultiplier = null;
        $maxDamageRating = null;
        $archetype = null;
        $requiredStrength = null;
        $requiredWit = null;
        $requiredDexterity = null;
        $requiredConstitution = null;
        $flatCritBonus = null;
        $critChanceBonus = null;
        $adArmor = null;
        $dodgeBonus = null;
        $armorSubtype = null;
        $offhandApBonus = 0;
        $parryRating = 0;

        if ($item instanceof Weapon) {
            $minDamage = $item->getMinDamage();
            $maxDamage = $item->getMaxDamage();
            $damageType = $item->getDamageType()->value;
            $accuracyBonus = $item->getAccuracyBonus();
            $blockBreakRating = $item->getBlockBreakRating();
            $pierceMultiplier = $item->getPierceMultiplier();
            $maxDamageRating = $item->getMaxDamageRating();
            $archetype = $item->getArchetype()->value;
            $requiredStrength = $item->getRequiredStrength();
            $requiredWit = $item->getRequiredWit();
            $flatCritBonus = $item->getFlatCritBonus();
            $critChanceBonus = $item->getCritChanceBonus();
            $offhandApBonus = $item->getItemType() === ItemType::OFFHAND_WEAPON ? 1 : 0;
        } elseif ($item instanceof Armor) {
            $requiredStrength = $item->getRequiredStrength();
            $requiredWit = $item->getRequiredWit();
            $requiredDexterity = $item->getRequiredDexterity();
            $requiredConstitution = $item->getRequiredConstitution();
            $adArmor = $item->getAdArmor();
            $dodgeBonus = $item->getDodgeBonus();
            $armorSubtype = $item->getSubtype()->value;
            $archetype = $item->getArchetype();
        } elseif ($item instanceof Seal) {
            $minDamage = $item->getMinDamage();
            $maxDamage = $item->getMaxDamage();
            $archetype = $item->getArchetype()->value;
            $requiredStrength = $item->getRequiredStrength();
            $requiredWit = $item->getRequiredWit();
            $flatCritBonus = $item->getFlatCritBonus();
            $critChanceBonus = $item->getCritChanceBonus();
        }

        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'type' => $type,
            'quantity' => $quantity,
            'min_damage' => $minDamage,
            'max_damage' => $maxDamage,
            'damage_type' => $damageType,
            'accuracy_bonus' => $accuracyBonus,
            'block_break_rating' => $blockBreakRating,
            'pierce_multiplier' => $pierceMultiplier,
            'max_damage_rating' => $maxDamageRating,
            'archetype' => $archetype,
            'required_strength' => $requiredStrength,
            'required_wit' => $requiredWit,
            'required_dexterity' => $requiredDexterity,
            'required_constitution' => $requiredConstitution,
            'flat_crit_bonus' => $flatCritBonus,
            'crit_chance_bonus' => $critChanceBonus,
            'ad_armor' => $adArmor,
            'dodge_bonus' => $dodgeBonus,
            'armor_subtype' => $armorSubtype,
            'defensive_ap_bonus' => 0,
            'offhand_ap_bonus' => $offhandApBonus,
            'parry_rating' => $parryRating,
            'required_level' => $item->getRequiredLevel(),
        ];
    }
}
