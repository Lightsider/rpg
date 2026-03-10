<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Enumeration for types of battle log entries.
 */
enum BattleLogType: string
{
    case MOVE = 'move';
    case HIT = 'hit';
    case BLOCK = 'block';
    case MISS = 'miss';
    case DEATH = 'death';
}
