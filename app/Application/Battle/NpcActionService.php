<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Npc\Behavior\BehaviorModelRegistry;
use App\Domain\Npc\NpcCombatant;

/**
 * Application service that generates and queues turn actions for all
 * NPC participants in a battle. Called before round resolution.
 */
class NpcActionService
{
    public function __construct(
        private readonly BehaviorModelRegistry $behaviorRegistry,
    ) {
    }

    /**
     * Generate and queue actions for all NPC participants that haven't committed yet.
     */
    public function generateNpcActions(Battle $battle): void
    {
        foreach ($battle->getParticipants() as $participant) {
            if (!$participant->isNpc()) {
                continue;
            }

            if ($participant->getCurrentHp() <= 0) {
                continue;
            }

            if ($battle->isCharacterCommitted($participant->getId())) {
                continue;
            }

            if (!($participant instanceof NpcCombatant)) {
                continue;
            }

            $behaviorKey = $participant->getBehaviorModelKey();
            $behavior = $this->behaviorRegistry->get($behaviorKey);
            $actions = $behavior->decide($participant, $battle);

            foreach ($actions as $action) {
                $battle->queueAction($action);
            }

            $battle->commitCharacter($participant->getId());
        }
    }
}
