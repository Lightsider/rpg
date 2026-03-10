<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Character\Character;

/**
 * Stateless service to resolve combat attacks.
 *
 * Block handling is fully delegated to BlockPenetrationService —
 * CombatResolver itself contains no block-penetration logic.
 */
class CombatResolver
{
    private const float BASE_HIT_CHANCE = 0.8;

    public function __construct(
        private readonly BlockPenetrationService $blockPenetrationService,
        private readonly MaxDamageService $maxDamageService,
    ) {
    }

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

        // 3. Roll damage
        $maxDamageProc = $this->maxDamageService->checkMaxDamage($attacker);
        $damage = (float) ($maxDamageProc->triggered
            ? $weapon->getMaxDamage()
            : $weapon->rollBaseDamage());

        // 4. Add strength bonus
        $damage += $attacker->calculateStrengthBonus();

        // 5. Check critical
        $isCritical = false;
        if ($this->getRandom() < $attacker->calculateCritChance()) {
            $damage *= $attacker->calculateCritMultiplier();
            $isCritical = true;
        }

        $baseDamage = (int) round($damage);

        // 6. If blocked – delegate entirely to BlockPenetrationService
        if ($isBlocked) {
            $penetrationResult = $this->blockPenetrationService->checkBlockBreak(
                $attacker,
                $defender,
                $baseDamage,
            );

            return new AttackResult(
                damage: $penetrationResult->damage,
                isCritical: $isCritical,
                isDodged: false,
                isMiss: false,
                damageType: $damageType,
                isPierced: $penetrationResult->penetrated,
            );
        }

        return new AttackResult($baseDamage, $isCritical, false, false, $damageType);
    }

    /**
     * Helper to get random float between 0 and 1.
     * Protected so tests can override it.
     */
    protected function getRandom(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
