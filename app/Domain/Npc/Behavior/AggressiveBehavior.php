<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Pathfinder;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;

class AggressiveBehavior extends BaseHeuristicBehavior
{
    protected function evaluateMap(Combatant $npc, Combatant $target, Battle $battle): array
    {
        $actions = [];
        $apRemaining = $npc->getCurrentActionPoints();
        $map = $battle->getMap();

        if ($apRemaining <= 0) {
            return [];
        }

        $isAdjacent = $map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY());
        $currentAdjacencyCount = $this->getAdjacentEnemiesCount($npc->getX(), $npc->getY(), $npc, $battle);
        $enemies = $this->getAliveEnemies($npc, $battle);

        // Fear mechanic: If we are adjacent to the target but surrounded by >= 2 enemies,
        // we might want to retreat — unless a teammate is engaging the same enemies.
        if ($isAdjacent && ($currentAdjacencyCount < 2 || $this->hasTeammateEngagingSameEnemies($npc, $battle, $npc->getX(), $npc->getY()))) {
            return []; // Safe/adjacent, or has teammate nearby -> stay and fight.
        }

        // If we're in a dangerous position (adjacent to 2+ enemies, no teammate help),
        // try to retreat to a safer nearby tile using BFS.
        if ($isAdjacent && $currentAdjacencyCount >= 2) {
            $retreatStep = $this->findRetreatStep($npc, $battle, $enemies);
            if ($retreatStep !== null) {
                $actions[] = new TurnAction(
                    characterId: $npc->getId(),
                    type: ActionType::MOVE,
                    fromX: $npc->getX(),
                    fromY: $npc->getY(),
                    toX: $retreatStep['x'],
                    toY: $retreatStep['y']
                );
                return $actions;
            }
            // No retreat possible — stay and fight
            return [];
        }

        // Not adjacent — use BFS to approach the target
        $step = $this->findBestApproachStep($npc, $target, $battle);
        if ($step !== null) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::MOVE,
                fromX: $npc->getX(),
                fromY: $npc->getY(),
                toX: $step['x'],
                toY: $step['y']
            );
        }

        return $actions;
    }

    protected function allocateActionPoints(Combatant $npc, Combatant $target, Battle $battle, bool $hasMoved = false): array
    {
        $actions = [];
        $baseAp = $npc->getCurrentActionPoints();
        if ($hasMoved) {
            $baseAp = max(0, $baseAp - 1);
        }
        $bonusOffhandAp = $npc->getBonusOffhandAP();
        $map = $battle->getMap();

        if ($hasMoved || !$map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY())) {
            // Cannot attack, dump into blocks.
            // If we moved, we only have remaining baseAp. If we didn't move, we have baseAp + bonusOffhandAp.
            $blocksCount = $hasMoved ? $baseAp : ($baseAp + $bonusOffhandAp);
            return $this->createBlockActions($npc, $blocksCount);
        }

        $expectedIncoming = $this->calculateExpectedIncomingDamage($npc, $battle, 1);
        
        $isPanicking = ($expectedIncoming >= $npc->getCurrentHp() * 0.5) || 
                       ($npc->getCurrentHp() < $npc->getMaxHp() * 0.3);

        if ($isPanicking) {
            // Panic Mode: Dump all base AP into blocks to survive
            $blocksToPerform = $baseAp;
            if ($blocksToPerform > 0) {
                $actions = array_merge($actions, $this->createBlockActions($npc, $blocksToPerform));
                $baseAp = 0;
            }
            // But we can still use our free offhand AP to attack if we have a dagger!
            if ($bonusOffhandAp > 0) {
                $actions[] = new TurnAction(
                    characterId: $npc->getId(),
                    type: ActionType::ATTACK_OFFHAND,
                    targetZone: $this->selectTargetZone($npc, 0),
                    targetId: $target->getId()
                );
            }
            return $actions;
        }

        // Full Aggression Mode
        $attacksToPerform = min($npc->getMaxAttacks(), $baseAp);
        for ($i = 0; $i < $attacksToPerform; $i++) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::ATTACK,
                targetZone: $this->selectTargetZone($npc, $i),
                targetId: $target->getId()
            );
            $baseAp--;
        }

        // Offhand attack
        if ($bonusOffhandAp > 0) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::ATTACK_OFFHAND,
                targetZone: $this->selectTargetZone($npc, $attacksToPerform),
                targetId: $target->getId()
            );
        }

        // Any remaining base AP goes to blocks
        if ($baseAp > 0) {
            $actions = array_merge($actions, $this->createBlockActions($npc, $baseAp));
        }

        return $actions;
    }
}
