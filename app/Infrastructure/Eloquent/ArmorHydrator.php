<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Domain\Armor\Shield;
use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
use App\Infrastructure\Eloquent\Models\ItemModel;

class ArmorHydrator
{
    public function fromItem(ItemModel $item): Armor
    {
        if ($item->type === 'shield') {
            return new Shield(
                id: $item->id,
                name: $item->name,
                blockResistRating: $item->block_rating ?? 40,
                requiredStrength: $item->required_strength ?? 0,
                adArmor: (float) $item->ad_armor,
                dodgeBonus: (float) $item->dodge_bonus,
                requiredLevel: (int) ($item->required_level ?? 1),
            );
        }

        return new Armor(
            id: $item->id,
            name: $item->name,
            adArmor: (float)$item->ad_armor,
            dodgeBonus: (float)$item->dodge_bonus,
            subtype: ArmorSubtype::from($item->armor_subtype ?? 'body'),
            requiredStrength: $item->required_strength ?? 0,
            requiredWit: $item->required_wit ?? 0,
            requiredDexterity: $item->required_dexterity ?? 0,
            requiredConstitution: $item->required_constitution ?? 0,
            requiredLevel: (int) ($item->required_level ?? 1),
            archetype: $item->archetype ?? 'universal',
        );
    }
}
