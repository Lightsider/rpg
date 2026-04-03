<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * Enumeration for types of battle log entries.
 */
enum BattleLogType: string
{
    case MOVE = 'move';
    case ATTACK = 'attack';
    case HIT = 'hit';
    case CRIT = 'crit';
    case BLOCK = 'block';
    case BLOCK_BREAK = 'block_break';
    case MAX_DAMAGE = 'max_damage';
    case DODGE = 'dodge';
    case DEATH = 'death';
    case SKIP = 'skip';
    case VICTORY = 'victory';
}
