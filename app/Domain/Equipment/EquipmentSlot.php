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

    case SEAL_1 = 'seal_1';
    case SEAL_2 = 'seal_2';
    case SEAL_3 = 'seal_3';
    case SEAL_4 = 'seal_4';
}
