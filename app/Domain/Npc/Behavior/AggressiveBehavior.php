<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
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

        // Fear mechanic: If we are adjacent to the target but surrounded by >= 2 enemies, 
        // we might want to step away into a 1v1 tile if it still keeps us adjacent to the target.
        if ($isAdjacent && $currentAdjacencyCount < 2) {
            return []; // Safe and adjacent, stay here.
        }

        $bestCell = null;
        $bestScore = PHP_INT_MAX; // Lower score is better

        // Evaluate all 8 surrounding cells for a better position
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) continue;

                $nx = $npc->getX() + $dx;
                $ny = $npc->getY() + $dy;

                if (!$map->isWithinBounds($nx, $ny)) continue;
                if ($this->isCellOccupied($nx, $ny, $npc->getId(), $battle)) continue;

                $dist = abs($nx - $target->getX()) + abs($ny - $target->getY());
                $enemiesAdjacent = $this->getAdjacentEnemiesCount($nx, $ny, $npc, $battle);

                // Scoring heuristic:
                // Base cost is distance to target.
                // Fear penalty: +10 score for every enemy beyond the first one adjacent to this cell.
                $fearPenalty = max(0, $enemiesAdjacent - 1) * 10;
                
                $score = $dist + $fearPenalty;

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestCell = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        // Only move if the best cell is actually better than staying put (if we are adjacent but scared)
        // If we are currently at distance 1, our current score is 1 + max(0, current-1)*10.
        $currentDist = abs($npc->getX() - $target->getX()) + abs($npc->getY() - $target->getY());
        $currentScore = $currentDist + (max(0, $currentAdjacencyCount - 1) * 10);

        if ($bestCell !== null && $bestScore < $currentScore) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::MOVE,
                fromX: $npc->getX(),
                fromY: $npc->getY(),
                toX: $bestCell['x'],
                toY: $bestCell['y']
            );
        } elseif (!$isAdjacent && $bestCell !== null) {
            // Even if score isn't strictly better than current, if we are not adjacent, we MUST move to close the gap.
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
        $baseAp = $npc->getCurrentActionPoints();
        $bonusOffhandAp = $npc->getBonusOffhandAP();
        $map = $battle->getMap();

        if (!$map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY())) {
            // Cannot attack, dump into blocks
            return $this->createBlockActions($npc, $baseAp + $bonusOffhandAp);
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
                $zones = TargetZone::cases();
                $actions[] = new TurnAction(
                    characterId: $npc->getId(),
                    type: ActionType::ATTACK_OFFHAND,
                    targetZone: $zones[array_rand($zones)],
                    targetId: $target->getId()
                );
            }
            return $actions;
        }

        // Full Aggression Mode
        $attacksToPerform = min($npc->getMaxAttacks(), $baseAp);
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

        // Offhand attack
        if ($bonusOffhandAp > 0) {
            $actions[] = new TurnAction(
                characterId: $npc->getId(),
                type: ActionType::ATTACK_OFFHAND,
                targetZone: $zones[array_rand($zones)],
                targetId: $target->getId()
            );
        }

        // Any remaining base AP goes to blocks
        if ($baseAp > 0) {
            $actions = array_merge($actions, $this->createBlockActions($npc, $baseAp));
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
