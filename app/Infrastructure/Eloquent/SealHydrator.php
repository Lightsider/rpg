<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Domain\Seal\Seal;
use App\Domain\Weapon\WeaponArchetype;
use App\Infrastructure\Eloquent\Models\ItemModel;

class SealHydrator
{
    public function fromItem(ItemModel $item): Seal
    {
        return new Seal(
            id: $item->id,
            name: $item->name,
            minDamage: (float) $item->min_damage,
            maxDamage: (float) $item->max_damage,
            flatCritBonus: (float) $item->flat_crit_bonus,
            critChanceBonus: (float) ($item->crit_chance_bonus ?? 0),
            archetype: WeaponArchetype::from($item->archetype ?? 'hybrid'),
            requiredStrength: $item->required_strength ?? 0,
            requiredWit: $item->required_wit ?? 0,
            requiredLevel: (int) ($item->required_level ?? 1),
        );
    }
}
