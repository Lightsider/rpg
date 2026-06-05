<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Pathfinder;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;

class DefensiveBehavior extends BaseHeuristicBehavior
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

        if ($isAdjacent) {
            $isLowHp = $npc->getCurrentHp() < $npc->getMaxHp() * 0.3;
            if (!$isLowHp) {
                return [];
            }

            // Low HP and adjacent -> try to retreat using BFS
            $enemies = $this->getAliveEnemies($npc, $battle);
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
            }

            return $actions;
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
        // AP pool calculation
        $baseAp = $npc->getCurrentActionPoints();
        if ($hasMoved) {
            $baseAp = max(0, $baseAp - 1);
        }
        $bonusDefensiveAp = $npc->getBonusDefensiveAP();
        
        $map = $battle->getMap();
        
        if ($hasMoved || !$map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY())) {
            // Cannot/should not attack, just dump all into blocks
            $totalApForBlocks = $baseAp + $bonusDefensiveAp;
            return $this->createBlockActions($npc, $totalApForBlocks);
        }

        // Count adjacent enemies to decide stance
        $adjacentEnemies = $this->getAdjacentEnemiesCount($npc->getX(), $npc->getY(), $npc, $battle);

        $attacksToPerform = 0;
        if ($adjacentEnemies > 1) {
            // Defense Position: 1 attack, rest blocks
            $attacksToPerform = 1;
        } else {
            // Attack Position: Up to 2 attacks, rest blocks
            $attacksToPerform = min(2, $npc->getMaxAttacks());
        }

        // Ensure we don't try to perform more attacks than base AP allows
        $attacksToPerform = min($attacksToPerform, $baseAp);

        $zones = TargetZone::cases();
        for ($i = 0; $i < $attacksToPerform; $i++) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::ATTACK,
                targetZone: $zones[array_rand($zones)],
                targetId: $target->getId()
            );
            $baseAp--;
        }

        // Dump remaining into blocks
        $blocksToPerform = $baseAp + $bonusDefensiveAp;
        if ($blocksToPerform > 0) {
            $actions = array_merge($actions, $this->createBlockActions($npc, $blocksToPerform));
        }

        return $actions;
    }
}
