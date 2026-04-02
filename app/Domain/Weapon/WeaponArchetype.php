<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

/**
 * Defines the combat archetype of a weapon.
 */
enum WeaponArchetype: string
{
    case STABLE = 'stable';       // High base damage, consistent output
    case CRIT = 'crit';           // Low base damage, high burst (flat crit bonus)
    case HYBRID = 'hybrid';       // Balanced damage + moderate crit
}
