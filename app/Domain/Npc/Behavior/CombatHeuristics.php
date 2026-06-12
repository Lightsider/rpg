<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Pathfinder;
use App\Domain\Weapon\Dagger;

trait CombatHeuristics
{
    /**
     * Calculates the expected damage per swing an attacker can deal to a defender.
     * Uses a heuristic average ignoring armor specifics but accounting for dodge and parry.
     */
    protected function calculateExpectedDamage(Combatant $attacker, Combatant $defender): float
    {
        $weapon = $attacker->getWeaponForCombat();
        $baseDamage = $weapon->getMaxDamage() * 0.7; // rough average of max damage

        $damage = $baseDamage + $attacker->getSealsBaseDamage();
        
        if (!($weapon instanceof Dagger)) {
            $damage += $attacker->calculateStrengthBonus();
        }

        // Apply hit chance (1 - dodge - parry)
        $dodgeChance = $defender->calculateDodgeChance();
        $parryChance = $defender->calculateParryChance();
        $hitChance = max(0, 1.0 - $dodgeChance - $parryChance);

        return $damage * $hitChance;
    }

    /**
     * Estimates incoming damage from all adjacent enemies over a given number of turns.
     */
    protected function calculateExpectedIncomingDamage(Combatant $npc, Battle $battle, int $turns = 2): float
    {
        $incomingDamage = 0.0;
        $map = $battle->getMap();

        foreach ($battle->getParticipants() as $participant) {
            // Only care about alive enemies
            if ($participant->getCurrentHp() <= 0) {
                continue;
            }
            if ($battle->getParticipantTeam($participant->getId()) === $battle->getParticipantTeam($npc->getId())) {
                continue;
            }

            // Check if adjacent
            if ($map->isAdjacent($npc->getX(), $npc->getY(), $participant->getX(), $participant->getY())) {
                $damagePerSwing = $this->calculateExpectedDamage($participant, $npc);
                // Assume 2 swings per turn roughly
                $swingsPerTurn = min(2, $participant->getMaxAttacks());
                $incomingDamage += ($damagePerSwing * $swingsPerTurn * $turns);
            }
        }

        return $incomingDamage;
    }

    /**
     * Scores enemies based on their vulnerability and distance.
     * Returns a sorted array of enemies (highest score first).
     * @param Combatant[] $enemies
     * @return Combatant[]
     */
    protected function scoreTargets(Combatant $npc, array $enemies, Battle $battle): array
    {
        $scored = [];
        $map = $battle->getMap();

        foreach ($enemies as $enemy) {
            if ($enemy->getCurrentHp() <= 0) {
                continue;
            }

            $expectedDmg = $this->calculateExpectedDamage($npc, $enemy);
            
            // Score components
            $killPotential = $expectedDmg / max(1, $enemy->getCurrentHp()); 
            
            // Distance penalty (Manhattan distance)
            $distX = abs($npc->getX() - $enemy->getX());
            $distY = abs($npc->getY() - $enemy->getY());
            $distance = max($distX, $distY);
            
            $distancePenalty = $distance * 0.5;

            // Final score: High kill potential is good, long distance is bad
            $score = ($killPotential * 10) - $distancePenalty;

            if ($distance <= 1) {
                // Highly prefer targets we are already adjacent to
                $score += 10000;
            }

            $scored[] = ['enemy' => $enemy, 'score' => $score];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($item) => $item['enemy'], $scored);
    }

    protected function hasTeammateEngagingSameEnemies(Combatant $npc, Battle $battle, int $x, int $y): bool
    {
        $myTeam = $battle->getParticipantTeam($npc->getId());
        $map = $battle->getMap();
        
        $adjacentEnemies = [];
        foreach ($battle->getParticipants() as $p) {
            if ($p->getId() === $npc->getId() || $p->getCurrentHp() <= 0) {
                continue;
            }
            if ($battle->getParticipantTeam($p->getId()) !== $myTeam) {
                if ($map->isAdjacent($x, $y, $p->getX(), $p->getY())) {
                    $adjacentEnemies[] = $p;
                }
            }
        }

        if (empty($adjacentEnemies)) {
            return false;
        }

        foreach ($battle->getParticipants() as $teammate) {
            if ($teammate->getId() === $npc->getId() || $teammate->getCurrentHp() <= 0) {
                continue;
            }
            if ($battle->getParticipantTeam($teammate->getId()) === $myTeam) {
                foreach ($adjacentEnemies as $enemy) {
                    if ($map->isAdjacent($teammate->getX(), $teammate->getY(), $enemy->getX(), $enemy->getY())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Build the "isBlocked" callback for Pathfinder, treating occupied cells as obstacles.
     *
     * @return callable(int, int): bool
     */
    protected function buildBlockedCallback(Battle $battle, int $npcId): callable
    {
        return fn(int $x, int $y): bool => $battle->isCellOccupied($x, $y, $npcId);
    }

    /**
     * Find all unoccupied cells adjacent to a target enemy.
     * These are the "goal cells" the NPC wants to reach to attack.
     *
     * @return array<int, array{x: int, y: int}>
     */
    protected function getAttackPositionsAround(Combatant $target, Battle $battle, int $npcId): array
    {
        $map = $battle->getMap();
        $positions = [];

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                $nx = $target->getX() + $dx;
                $ny = $target->getY() + $dy;

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }
                if (!$battle->isCellOccupied($nx, $ny, $npcId)) {
                    $positions[] = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        return $positions;
    }

    /**
     * Use BFS to find the best first step toward attacking the target.
     * Scores goal cells by flanking bonus and BFS distance, then returns the first step
     * toward the best reachable goal.
     *
     * @return array{x: int, y: int}|null
     */
    protected function findBestApproachStep(
        Combatant $npc,
        Combatant $target,
        Battle $battle
    ): ?array {
        $map = $battle->getMap();
        $isBlocked = $this->buildBlockedCallback($battle, $npc->getId());

        // Already adjacent — no move needed
        if ($map->isAdjacent($npc->getX(), $npc->getY(), $target->getX(), $target->getY())) {
            return null;
        }

        // Gather all open tiles adjacent to the target
        $goalCells = $this->getAttackPositionsAround($target, $battle, $npc->getId());
        if (empty($goalCells)) {
            // Target is completely surrounded — just get as close as possible
            return Pathfinder::findNextStep(
                $map,
                $npc->getX(), $npc->getY(),
                $target->getX(), $target->getY(),
                $isBlocked
            );
        }

        // Use multi-goal BFS: find the first step toward the closest reachable goal
        $result = Pathfinder::findNextStepToAny(
            $map,
            $npc->getX(), $npc->getY(),
            $goalCells,
            $isBlocked
        );

        if ($result !== null) {
            return ['x' => $result['x'], 'y' => $result['y']];
        }

        return $this->findFallbackStep($npc, $target, $battle);
    }

    /**
     * Greedy fallback if BFS cannot find a path (e.g. target is completely surrounded by units).
     * Moves to the adjacent cell that minimizes distance to the target.
     *
     * @return array{x: int, y: int}|null
     */
    protected function findFallbackStep(
        Combatant $npc,
        Combatant $target,
        Battle $battle
    ): ?array {
        $map = $battle->getMap();
        $bestCell = null;
        $bestDist = PHP_INT_MAX;

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) continue;
                $nx = $npc->getX() + $dx;
                $ny = $npc->getY() + $dy;
                
                if (!$map->isWithinBounds($nx, $ny)) continue;
                if ($battle->isCellOccupied($nx, $ny, $npc->getId())) continue;

                $dist = abs($nx - $target->getX()) + abs($ny - $target->getY());
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestCell = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        return $bestCell;
    }

    /**
     * Find a safe retreat position for a fleeing NPC.
     * 
     * The bot wants to fight but not suicide. It will look for a tile that:
     * - Reduces the number of adjacent enemies (ideally to 1 or 0)
     * - Is only a few tiles away (not running to the corner)
     * - Prefers tiles near the map border (harder to surround)
     * 
     * Returns the first BFS step toward the best retreat tile, or null if staying is best.
     *
     * @param Combatant[] $enemies All alive enemies
     * @return array{x: int, y: int}|null
     */
    protected function findRetreatStep(
        Combatant $npc,
        Battle $battle,
        array $enemies
    ): ?array {
        $map = $battle->getMap();
        $isBlocked = $this->buildBlockedCallback($battle, $npc->getId());
        $myTeam = $battle->getParticipantTeam($npc->getId());

        // How many enemies are adjacent to us right now
        $currentAdjacentEnemies = 0;
        foreach ($enemies as $enemy) {
            if ($map->isAdjacent($npc->getX(), $npc->getY(), $enemy->getX(), $enemy->getY())) {
                $currentAdjacentEnemies++;
            }
        }

        // Scan tiles within a short radius (up to 3 tiles away via BFS)
        // to find one that reduces the threat without running away from the fight
        $maxRetreatDistance = 3;
        $candidates = $this->findCandidateTilesInRadius(
            $map, $npc->getX(), $npc->getY(), $maxRetreatDistance, $isBlocked
        );

        $bestTile = null;
        $bestScore = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $cx = $candidate['x'];
            $cy = $candidate['y'];
            $bfsDist = $candidate['dist'];

            // Count enemies adjacent to this candidate tile
            $adjacentEnemies = 0;
            foreach ($enemies as $enemy) {
                if ($map->isAdjacent($cx, $cy, $enemy->getX(), $enemy->getY())) {
                    $adjacentEnemies++;
                }
            }

            // Skip tiles that are MORE dangerous than where we are now
            if ($adjacentEnemies >= $currentAdjacentEnemies) {
                continue;
            }

            // Score: fewer adjacent enemies is better, closer is better
            // Adjacent enemies is the primary factor, BFS distance is tiebreaker
            $score = ($adjacentEnemies * 100) + ($bfsDist * 10);

            // Bonus for border tiles (harder to surround from all sides)
            $isOnBorder = ($cx === 0 || $cy === 0 || $cx === $map->getWidth() - 1 || $cy === $map->getHeight() - 1);
            if ($isOnBorder) {
                $score -= 5;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $bestTile = ['x' => $cx, 'y' => $cy];
            }
        }

        if ($bestTile === null) {
            return null;
        }

        // Use BFS to get the first step toward the best retreat tile
        return Pathfinder::findNextStep(
            $map,
            $npc->getX(), $npc->getY(),
            $bestTile['x'], $bestTile['y'],
            $isBlocked
        );
    }

    /**
     * BFS to find all reachable tiles within a given radius.
     *
     * @param callable(int, int): bool $isBlocked
     * @return array<int, array{x: int, y: int, dist: int}>
     */
    private function findCandidateTilesInRadius(
        \App\Domain\Battle\Map $map,
        int $startX,
        int $startY,
        int $maxDist,
        callable $isBlocked
    ): array {
        $directions = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1],
        ];

        $visited = ["$startX:$startY" => true];
        $queue = [[$startX, $startY, 0]];
        $results = [];

        while (!empty($queue)) {
            [$cx, $cy, $dist] = array_shift($queue);

            if ($dist >= $maxDist) {
                continue;
            }

            foreach ($directions as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                $key = "$nx:$ny";

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }
                if (isset($visited[$key])) {
                    continue;
                }
                if ($isBlocked($nx, $ny)) {
                    $visited[$key] = true;
                    continue;
                }

                $visited[$key] = true;
                $newDist = $dist + 1;
                $results[] = ['x' => $nx, 'y' => $ny, 'dist' => $newDist];

                $queue[] = [$nx, $ny, $newDist];
            }
        }

        return $results;
    }
}
