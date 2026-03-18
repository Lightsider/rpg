<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Log;

/**
 * Stateless service to resolve combat attacks.
 *
 * Block handling is fully delegated to BlockPenetrationService —
 * CombatResolver itself contains no block-penetration logic.
 *
 * Dodge and Critical Hit use PRNG (Pseudo-Random Number Generation)
 * with bad-luck protection to reduce streaks of bad luck.
 */
class CombatResolver
{
    private readonly ?PseudoRandomService $dodgePRNG;
    private readonly ?PseudoRandomService $critPRNG;

    public function __construct(
        private readonly BlockPenetrationService $blockPenetrationService,
        private readonly MaxDamageService $maxDamageService,
        ?PseudoRandomService $dodgePRNG = null,
        ?PseudoRandomService $critPRNG = null,
    ) {
        // If PRNG services are not provided, create them with default config
        // This allows the service to work both in production and in tests
        if ($dodgePRNG === null) {
            $dodgePRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(
                    k: 150,
                    maxFinalChance: 0.80,
                    prngScale: 0.25,
                    debug: false,
                )
            );
        }
        if ($critPRNG === null) {
            $critPRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(
                    k: 150,
                    maxFinalChance: 0.80,
                    prngScale: 0.25,
                    debug: false,
                )
            );
        }
        $this->dodgePRNG = $dodgePRNG;
        $this->critPRNG = $critPRNG;
    }

    /**
     * Resolves a single attack from attacker to defender.
     */
    public function resolveAttack(Character $attacker, Character $defender, bool $isBlocked): AttackResult
    {
        $weapon = $attacker->getWeapon();
        $damageType = $weapon->getDamageType();

        // 1. Check dodge with PRNG
        $isDodged = $this->checkDodge($defender);
        if ($isDodged) {
            return new AttackResult(0, false, true, false, $damageType);
        }

        // 2. Roll damage from weapon
        $maxDamageProc = $this->maxDamageService->checkMaxDamage($attacker);
        $baseDamage = (float) ($maxDamageProc->triggered
            ? $weapon->getMaxDamage()
            : $weapon->rollBaseDamage());

        // 3. Add strength bonus (stat amplification)
        $strengthBonus = $attacker->calculateStrengthBonus();
        $totalDamage = $baseDamage + $strengthBonus;

        // 4. Check critical with PRNG
        $isCritical = false;
        $critResult = $this->checkCritical($attacker);
        if ($critResult->success) {
            // New gear-first crit: total_damage + weapon's flat crit bonus
            $totalDamage += $weapon->getFlatCritBonus();
            $isCritical = true;
        }

        $finalDamage = (int) round($totalDamage);

        // 5. If blocked – delegate entirely to BlockPenetrationService
        if ($isBlocked) {
            $penetrationResult = $this->blockPenetrationService->checkBlockBreak(
                $attacker,
                $defender,
                $finalDamage,
            );

            return new AttackResult(
                damage: $penetrationResult->damage,
                isCritical: $isCritical,
                isDodged: false,
                isMiss: false,
                damageType: $damageType,
                isPierced: $penetrationResult->penetrated,
                isMaxDamage: $maxDamageProc->triggered,
            );
        }

        return new AttackResult($finalDamage, $isCritical, false, false, $damageType, false, $maxDamageProc->triggered);
    }

    /**
     * Check dodge with PRNG bad-luck protection.
     */
    private function checkDodge(Character $defender): bool
    {
        $baseDodgeChance = $defender->calculateDodgeChance();
        $failStreak = $defender->getDodgeFailStreak();

        $result = $this->dodgePRNG->rollWithPRNG($baseDodgeChance, $failStreak);

        if ($result->success) {
            $defender->resetDodgeFailStreak();
        } else {
            $defender->incrementDodgeFailStreak();
        }

        if ($result->baseChance !== null) {
            Log::debug('DodgeCheck', [
                'defender_id' => $defender->getId(),
                'base_chance' => $result->baseChance,
                'failures' => $failStreak,
                'final_chance' => $result->finalChance,
                'roll' => $result->randomRoll,
                'dodged' => $result->success,
            ]);
        }

        return $result->success;
    }

    /**
     * Check critical hit with PRNG bad-luck protection.
     */
    private function checkCritical(Character $attacker): \App\Domain\Battle\PseudoRandom\PRNGResult
    {
        $baseCritChance = $attacker->calculateCritChance();
        $failStreak = $attacker->getCritFailStreak();

        $result = $this->critPRNG->rollWithPRNG($baseCritChance, $failStreak);

        if ($result->success) {
            $attacker->resetCritFailStreak();
        } else {
            $attacker->incrementCritFailStreak();
        }

        if ($result->baseChance !== null) {
            Log::debug('CritCheck', [
                'attacker_id' => $attacker->getId(),
                'base_chance' => $result->baseChance,
                'failures' => $failStreak,
                'final_chance' => $result->finalChance,
                'roll' => $result->randomRoll,
                'crit' => $result->success,
            ]);
        }

        return $result;
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
