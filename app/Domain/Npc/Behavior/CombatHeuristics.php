<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
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
}
