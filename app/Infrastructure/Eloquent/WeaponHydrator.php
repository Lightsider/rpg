<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use App\Infrastructure\Eloquent\Models\ItemModel;

class WeaponHydrator
{
    public function fromItem(ItemModel $item): Weapon
    {
        return new Weapon(
            id: $item->id,
            name: $item->name,
            minDamage: $item->min_damage,
            maxDamage: $item->max_damage,
            damageType: DamageType::from(strtolower($item->damage_type ?? 'blunt')),
            accuracyBonus: $item->accuracy_bonus,
            blockBreakRating: $item->block_break_rating,
            pierceMultiplier: $item->pierce_multiplier,
            maxDamageRating: $item->max_damage_rating,
            archetype: WeaponArchetype::from($item->archetype ?? 'universal'),
            requiredStrength: $item->required_strength ?? 0,
            requiredWit: $item->required_wit ?? 0,
            flatCritBonus: $item->flat_crit_bonus ?? 0,
            critChanceBonus: (float) ($item->crit_chance_bonus ?? 0),
        );
    }

    public function fromLegacyName(?string $weaponType): Weapon
    {
        if ($weaponType === 'sword') {
            return new Weapon(1, 'Sword', 9, 11, DamageType::SLASHING, 0.0, 20, 0.50, 90, WeaponArchetype::UNIVERSAL, 7, 3, 3);
        }

        if ($weaponType === 'axe') {
            return new Weapon(2, 'Axe', 9, 11, DamageType::CHOPPING, 0.0, 60, 0.65, 0, WeaponArchetype::UNIVERSAL, 7, 3, 3);
        }

        return new Weapon(0, 'Fists', 1, 3, DamageType::BLUNT, 0.0, 0, 0.10, 0, WeaponArchetype::UNIVERSAL, 0, 0, 0);
    }
}
