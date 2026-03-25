<?php

declare(strict_types=1);

namespace App\Domain\Battle;

enum BattleState: string
{
    case WAITING = 'waiting';
    case ACTIVE = 'active';
    case RESOLVING = 'resolving';
    case FINISHED = 'finished';
}
