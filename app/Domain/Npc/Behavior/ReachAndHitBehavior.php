<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
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

        // If not adjacent, move toward the target first
        if (!$isAdjacent && $apRemaining > 0) {
            $moveTarget = $this->findBestMoveToward($npc, $target, $battle);
            if ($moveTarget !== null) {
                $actions[] = new TurnAction(
                    characterId: $npcId,
                    type: ActionType::MOVE,
                    fromX: $npc->getX(),
                    fromY: $npc->getY(),
                    toX: $moveTarget['x'],
                    toY: $moveTarget['y'],
                );
                $apRemaining--;
                $moved = true;

                // Re-check adjacency after the planned move
                $dx = abs($moveTarget['x'] - $target->getX());
                $dy = abs($moveTarget['y'] - $target->getY());
                $isAdjacent = $dx <= 1 && $dy <= 1;
            }
        }

        // Attack the target if adjacent
        if ($isAdjacent && !$moved) {
            $zones = TargetZone::cases();
            while ($apRemaining > 0 && $attacksUsed < $maxAttacks) {
                $zone = $zones[array_rand($zones)];
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

    private function isAdjacent(Combatant $a, Combatant $b): bool
    {
        $dx = abs($a->getX() - $b->getX());
        $dy = abs($a->getY() - $b->getY());
        return $dx <= 1 && $dy <= 1;
    }

    /**
     * @return array{x: int, y: int}|null
     */
    private function findBestMoveToward(Combatant $npc, Combatant $target, Battle $battle): ?array
    {
        $map = $battle->getMap();
        $bestCell = null;
        $bestScore = PHP_INT_MAX;

        // Check all adjacent cells
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }

                $nx = $npc->getX() + $dx;
                $ny = $npc->getY() + $dy;

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }

                // Check if occupied
                if ($battle->isCellOccupied($nx, $ny, $npc->getId())) {
                    continue;
                }

                $dist = abs($nx - $target->getX()) + abs($ny - $target->getY());
                $score = $dist * 10;

                // REWARD: if moving here makes us adjacent to the target,
                // and a teammate is ALSO adjacent to the target, we heavily prefer this cell!
                // This satisfies "like 2 mates vs 1 enemy positions"
                if ($dist <= 1) {
                    if ($this->hasTeammateEngagingSameEnemies($npc, $battle, $nx, $ny)) {
                        $score -= 15; // Massive bonus for 2v1 positioning
                    }
                }

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestCell = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        return $bestCell;
    }

    /**
     * @return TurnAction[]
     */
    private function fillWithBlocks(Combatant $npc, Battle $battle, int $count): array
    {
        $actions = [];
        $zones = TargetZone::cases();
        $used = [];

        for ($i = 0; $i < $count && $i < count($zones); $i++) {
            $zone = $zones[$i % count($zones)];
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::DEFEND,
                targetZone: $zone,
            );
        }

        return $actions;
    }
}
