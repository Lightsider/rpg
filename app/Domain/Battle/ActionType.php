<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Supported turn action types.
 */
enum ActionType: string
{
    case ATTACK = 'attack';
    case ATTACK_OFFHAND = 'attack_offhand';
    case DEFEND = 'defend';
    case MOVE = 'move';
}
