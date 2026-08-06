<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Pathfinder;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;

/**
 * Default NPC behavior: move toward the nearest enemy, attack if adjacent,
 * spend remaining AP on defensive blocks.
 */
class ReachAndHitBehavior implements BehaviorModelInterface
{
    use CombatHeuristics;

    public function decide(Combatant $npc, Battle $battle): array
    {
        $actions = [];
        $npcId = $npc->getId();
        $apRemaining = $npc->getCurrentActionPoints();
        $attacksUsed = 0;
        $maxAttacks = $npc->getMaxAttacks();

        $target = $this->findNearestEnemy($npc, $battle);
        if (!$target) {
            // No target — spend all AP on blocks
            return $this->fillWithBlocks($npc, $battle, $apRemaining);
        }

        $isAdjacent = $this->isAdjacent($npc, $target);
        $moved = false;

        // If not adjacent, move toward the target using BFS pathfinding
        if (!$isAdjacent && $apRemaining > 0) {
            $step = $this->findBestApproachStep($npc, $target, $battle);
            if ($step !== null) {
                $actions[] = new TurnAction(
                    characterId: $npcId,
                    type: ActionType::MOVE,
                    fromX: $npc->getX(),
                    fromY: $npc->getY(),
                    toX: $step['x'],
                    toY: $step['y'],
                );
                $apRemaining--;
                $moved = true;

                // Re-check adjacency after the planned move
                $dx = abs($step['x'] - $target->getX());
                $dy = abs($step['y'] - $target->getY());
                $isAdjacent = $dx <= 1 && $dy <= 1;
            }
        }

        // Attack the target if adjacent
        if ($isAdjacent && !$moved) {
                while ($apRemaining > 0 && $attacksUsed < $maxAttacks) {
                $zone = $this->selectTargetZone($npc, $attacksUsed);
                $actions[] = new TurnAction(
                    characterId: $npcId,
                    type: ActionType::ATTACK,
                    targetZone: $zone,
                    targetId: $target->getId(),
                );
                $apRemaining--;
                $attacksUsed++;
            }
        }

        // Spend any remaining AP on blocks
        if ($apRemaining > 0) {
            $blockActions = $this->fillWithBlocks($npc, $battle, $apRemaining);
            $actions = array_merge($actions, $blockActions);
        }

        return $actions;
    }

    private function findNearestEnemy(Combatant $npc, Battle $battle): ?Combatant
    {
        $npcTeam = $battle->getParticipantTeam($npc->getId());
        $nearest = null;
        $nearestDist = PHP_INT_MAX;

        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getId() === $npc->getId()) {
                continue;
            }
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }

            $theirTeam = $battle->getParticipantTeam($participant->getId());
            if ($npcTeam !== null && $theirTeam !== null && $npcTeam === $theirTeam) {
                continue;
            }

            $dist = abs($npc->getX() - $participant->getX()) + abs($npc->getY() - $participant->getY());
            if ($dist < $nearestDist) {
                $nearestDist = $dist;
                $nearest = $participant;
            }
        }

        return $nearest;
    }

    private function selectTargetZone(Combatant $npc, int $sequence): TargetZone
    {
        $zones = TargetZone::cases();

        return $zones[abs($npc->getId() + $sequence) % count($zones)];
    }

    private function isAdjacent(Combatant $a, Combatant $b): bool
    {
        $dx = abs($a->getX() - $b->getX());
        $dy = abs($a->getY() - $b->getY());
        return $dx <= 1 && $dy <= 1;
    }

    /**
     * @return TurnAction[]
     */
    private function fillWithBlocks(Combatant $npc, Battle $battle, int $count): array
    {
        $actions = [];
        $zones = TargetZone::cases();

        for ($i = 0; $i < $count && $i < count($zones); $i++) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::DEFEND,
                targetZone: $this->selectTargetZone($npc, $i),
            );
        }

        return $actions;
    }
}
