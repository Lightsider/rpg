<?php

declare(strict_types=1);

namespace App\Domain\Weapon;

/**
 * Supported damage types for weapons.
 */
enum DamageType: string
{
    case SLASH = 'slash';
    case BLUNT = 'blunt';
    case PIERCE = 'pierce';
    case CRUSH = 'crush';
}
