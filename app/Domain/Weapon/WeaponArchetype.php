<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

/**
 * Defines the combat archetype of a weapon.
 */
enum WeaponArchetype: string
{
    case TANK = 'tank';           // High base damage, no/low crit
    case CRIT = 'crit';           // Low base damage, high burst (flat crit bonus)
    case UNIVERSAL = 'universal'; // Balanced performance
}
