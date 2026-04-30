<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\TurnAction;

/**
 * Strategy interface for NPC AI behavior models.
 * Each implementation defines how an NPC selects its turn actions.
 */
interface BehaviorModelInterface
{
    /**
     * Generate turn actions for the given NPC in the current battle state.
     *
     * @return TurnAction[]
     */
    public function decide(Combatant $npc, Battle $battle): array;
}
