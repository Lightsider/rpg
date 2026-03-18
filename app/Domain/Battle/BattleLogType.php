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
    case BLOCK_BREAK = 'block_break';
    case MAX_DAMAGE = 'max_damage';
    case DODGE = 'dodge';
    case DEATH = 'death';
}
