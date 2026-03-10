<?php

declare(strict_types=1);

namespace App\Domain\Equipment;

enum EquipmentSlot: string
{
    case MAIN_HAND = 'main_hand';
    case OFF_HAND = 'off_hand';

    case HELMET = 'helmet';
    case GLOVES = 'gloves';
    case CHEST = 'chest';
    case LEGS = 'legs';

    case CHARM_1 = 'charm_1';
    case CHARM_2 = 'charm_2';
    case CHARM_3 = 'charm_3';
    case CHARM_4 = 'charm_4';
}
