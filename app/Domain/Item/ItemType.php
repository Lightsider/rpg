<?php

declare(strict_types=1);

namespace App\Domain\Item;

enum ItemType: string
{
    case WEAPON = 'weapon';
    case SHIELD = 'shield';
    case OFFHAND_WEAPON = 'offhand_weapon';
    case HELMET = 'helmet';
    case GLOVES = 'gloves';
    case CHEST_ARMOR = 'chest_armor';
    case LEG_ARMOR = 'leg_armor';
    case CHARM = 'charm';
}
