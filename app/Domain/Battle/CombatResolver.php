<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Character\Character;

/**
 * Stateless service to resolve combat attacks.
 */
class CombatResolver
{
    private const float BASE_HIT_CHANCE = 0.8;
    private const float BLOCK_REDUCED_DAMAGE_MULTIPLIER = 0.5;
    private const float MIN_RANDOM = 0.0;
    private const float MAX_RANDOM = 1.0;

    /**
     * Resolves a single attack from attacker to defender.
     */
    public function resolveAttack(Character $attacker, Character $defender, bool $isBlocked): AttackResult
    {
        $weapon = $attacker->getWeapon();
        $damageType = $weapon->getDamageType();

        // 1. Check dodge
        if ($this->getRandom() < $defender->calculateDodgeChance()) {
            return new AttackResult(0, false, true, false, $damageType);
        }

        // 2. Calculate hit chance
        $hitChance = self::BASE_HIT_CHANCE + $weapon->getAccuracyBonus();
        if ($this->getRandom() > $hitChance) {
            return new AttackResult(0, false, false, true, $damageType);
        }

        // 3. Roll base damage
        $damage = (float) $weapon->rollBaseDamage();

        // 4. Add strength bonus
        $damage += $attacker->calculateStrengthBonus();

        // 5. Check critical
        $isCritical = false;
        if ($this->getRandom() < $attacker->calculateCritChance()) {
            $damage *= $attacker->calculateCritMultiplier();
            $isCritical = true;
        }

        // 6. If blocked
        if ($isBlocked) {
            if ($this->getRandom() < $weapon->getBlockBreakChance()) {
                $damage *= self::BLOCK_REDUCED_DAMAGE_MULTIPLIER;
            } else {
                $damage = 0.0;
            }
        }

        return new AttackResult((int) round($damage), $isCritical, false, false, $damageType);
    }

    /**
     * Helper to get random float between 0 and 1.
     */
    private function getRandom(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
