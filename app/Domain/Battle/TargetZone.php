<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Supported target zones for actions.
 */
enum TargetZone: string
{
    case HEAD = 'head';
    case TORSO = 'torso';
    case LEFT_ARM = 'left_arm';
    case RIGHT_ARM = 'right_arm';
    case LEGS = 'legs';
}
