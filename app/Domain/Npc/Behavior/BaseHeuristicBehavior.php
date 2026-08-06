<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;

abstract class BaseHeuristicBehavior implements BehaviorModelInterface
{
    use CombatHeuristics;

    public function decide(Combatant $npc, Battle $battle): array
    {
        $actions = [];
        $enemies = $this->getAliveEnemies($npc, $battle);

        if (empty($enemies)) {
            return [];
        }

        // 1. Select the most optimal target based on expected damage and distance
        $target = $this->selectTarget($npc, $enemies, $battle);
        if (!$target) {
            return [];
        }

        // 2. Evaluate map and pathfind to the optimal position
        $moveActions = $this->evaluateMap($npc, $target, $battle);
        $hasMoved = !empty($moveActions);
        foreach ($moveActions as $move) {
            $actions[] = $move;
            // Update NPC position internally so the next steps know where it is
            $npc->setPosition($move->getToX(), $move->getToY());
        }

        // 3. Allocate remaining Action Points (attacks / blocks)
        $combatActions = $this->allocateActionPoints($npc, $target, $battle, $hasMoved);
        foreach ($combatActions as $combatAct) {
            $actions[] = $combatAct;
        }

        return $actions;
    }

    /**
     * @return Combatant[]
     */
    protected function getAliveEnemies(Combatant $npc, Battle $battle): array
    {
        $enemies = [];
        $myTeam = $battle->getParticipantTeam($npc->getId());

        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getId() === $npc->getId() || $participant->getCurrentHp() <= 0) {
                continue;
            }
            if ($battle->getParticipantTeam($participant->getId()) !== $myTeam) {
                $enemies[] = $participant;
            }
        }

        return $enemies;
    }

    protected function selectTarget(Combatant $npc, array $enemies, Battle $battle): ?Combatant
    {
        $scored = $this->scoreTargets($npc, $enemies, $battle);
        return $scored[0] ?? null;
    }

    protected function getAdjacentEnemiesCount(int $x, int $y, Combatant $npc, Battle $battle): int
    {
        $count = 0;
        $map = $battle->getMap();
        $myTeam = $battle->getParticipantTeam($npc->getId());

        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getId() === $npc->getId() || $participant->getCurrentHp() <= 0) {
                continue;
            }
            if ($battle->getParticipantTeam($participant->getId()) === $myTeam) {
                continue;
            }
            
            if ($map->isAdjacent($x, $y, $participant->getX(), $participant->getY())) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return TurnAction[]
     */
    protected function createBlockActions(Combatant $npc, int $count): array
    {
        $actions = [];

        for ($i = 0; $i < $count; $i++) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::DEFEND,
                targetZone: $this->selectTargetZone($npc, $i)
            );
        }

        return $actions;
    }

    protected function selectTargetZone(Combatant $npc, int $sequence): TargetZone
    {
        $zones = TargetZone::cases();

        return $zones[abs($npc->getId() + $sequence) % count($zones)];
    }

    /**
     * Define how the NPC evaluates the map and moves towards the target.
     * @return TurnAction[]
     */
    abstract protected function evaluateMap(Combatant $npc, Combatant $target, Battle $battle): array;

    /**
     * Define how the NPC allocates its AP between attacks and blocks.
     * @return TurnAction[]
     */
    abstract protected function allocateActionPoints(Combatant $npc, Combatant $target, Battle $battle, bool $hasMoved = false): array;
}
