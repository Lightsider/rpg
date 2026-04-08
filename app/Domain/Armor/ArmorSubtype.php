<?php

declare(strict_types=1);

namespace App\Domain\Armor;

enum ArmorSubtype: string
{
    case HELMET = 'helmet';
    case BODY = 'body';
    case BOOTS = 'boots';
    case GLOVES = 'gloves';

    case OFF_HAND = 'off_hand';
}
