<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
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

            // Low HP and adjacent -> try to move back (retreat)
            $bestCell = null;
            $bestScore = PHP_INT_MAX;

            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    if ($dx === 0 && $dy === 0) continue;

                    $nx = $npc->getX() + $dx;
                    $ny = $npc->getY() + $dy;

                    if (!$map->isWithinBounds($nx, $ny)) continue;
                    if ($this->isCellOccupied($nx, $ny, $npc->getId(), $battle)) continue;

                    // Must NOT be adjacent to the target
                    if ($map->isAdjacent($nx, $ny, $target->getX(), $target->getY())) continue;

                    $dist = abs($nx - $target->getX()) + abs($ny - $target->getY());
                    $enemiesAdjacent = $this->getAdjacentEnemiesCount($nx, $ny, $npc, $battle);

                    $score = ($enemiesAdjacent * 10) + $dist;

                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestCell = ['x' => $nx, 'y' => $ny];
                    }
                }
            }

            if ($bestCell !== null) {
                $actions[] = new TurnAction(
                    characterId: $npc->getId(),
                    type: ActionType::MOVE,
                    fromX: $npc->getX(),
                    fromY: $npc->getY(),
                    toX: $bestCell['x'],
                    toY: $bestCell['y']
                );
            }

            return $actions;
        }

        $bestCell = null;
        $bestDist = PHP_INT_MAX;

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) continue;

                $nx = $npc->getX() + $dx;
                $ny = $npc->getY() + $dy;

                if (!$map->isWithinBounds($nx, $ny)) continue;
                if ($this->isCellOccupied($nx, $ny, $npc->getId(), $battle)) continue;

                $dist = abs($nx - $target->getX()) + abs($ny - $target->getY());
                // For defensive, we just want to get as close as possible (no fear penalty)
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestCell = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        if ($bestCell !== null) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::MOVE,
                fromX: $npc->getX(),
                fromY: $npc->getY(),
                toX: $bestCell['x'],
                toY: $bestCell['y']
            );
        }

        return $actions;
    }

    protected function allocateActionPoints(Combatant $npc, Combatant $target, Battle $battle): array
    {
        $actions = [];
        // AP pool calculation
        $baseAp = $npc->getCurrentActionPoints(); // This usually drops to 2 if we moved
        $bonusDefensiveAp = $npc->getBonusDefensiveAP();
        
        $map = $battle->getMap();
        
        if (!$map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY())) {
            // Cannot attack, just dump all into blocks
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

    private function isCellOccupied(int $x, int $y, int $excludeId, Battle $battle): bool
    {
        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getId() === $excludeId || $participant->getCurrentHp() <= 0) {
                continue;
            }
            if ($participant->getX() === $x && $participant->getY() === $y) {
                return true;
            }
        }
        return false;
    }
}
