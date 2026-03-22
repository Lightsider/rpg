<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Log;

/**
 * Stateless service to resolve combat attacks.
 * Uses high-precision calculations (floats with rounding) and Fractional Damage Accumulation.
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
        if ($dodgePRNG === null) {
            $dodgePRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(k: 150, maxFinalChance: 0.80, prngScale: 0.25)
            );
        }
        if ($critPRNG === null) {
            $critPRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(k: 150, maxFinalChance: 0.80, prngScale: 0.25)
            );
        }
        $this->dodgePRNG = $dodgePRNG;
        $this->critPRNG = $critPRNG;
    }

    public function resolveAttack(Character $attacker, Character $defender, bool $isBlocked): AttackResult
    {
        $weapon = $attacker->getWeaponForCombat();
        $damageType = $weapon->getDamageType();

        // 1. Check dodge
        if ($this->checkDodge($defender)) {
            return new AttackResult(0, false, true, false, $damageType);
        }

        // 2. Base Damage (Weapon + Seals + Strength)
        $maxDamageProc = $this->maxDamageService->checkMaxDamage($attacker);
        $weaponDamage = (float)($maxDamageProc->triggered ? $weapon->getMaxDamage() : $weapon->rollBaseDamage());
        
        $currentDamage = $weaponDamage;
        $currentDamage += $attacker->getSealsBaseDamage();
        $currentDamage += $attacker->calculateStrengthBonus();

        // 3. Critical Hit
        $isCritical = false;
        if ($this->checkCritical($attacker)->success) {
            $isCritical = true;
            $currentDamage += (float)$weapon->getFlatCritBonus();
            $currentDamage += $attacker->getSealsFlatCritBonus();
        }

        // Round to 4 decimal places for precision management
        $currentDamage = round($currentDamage, 4);

        // 4. Fractional Accumulation
        // Add existing accumulator
        $totalWithAccumulator = $currentDamage + $attacker->getDamageAccumulator();
        $totalWithAccumulator = round($totalWithAccumulator, 4);
        
        // Final integer damage is floor of total
        $finalDamageInt = (int) floor($totalWithAccumulator);
        
        // Remainder goes back to accumulator
        $remainder = $totalWithAccumulator - $finalDamageInt;
        $attacker->setDamageAccumulator((float)round($remainder, 4));

        // 5. Block handling
        if ($isBlocked) {
            $penetrationResult = $this->blockPenetrationService->checkBlockBreak($attacker, $defender, $finalDamageInt);
            return new AttackResult(
                damage: $penetrationResult->damage,
                isCritical: $isCritical,
                isDodged: false,
                isMiss: false,
                damageType: $damageType,
                isPierced: $penetrationResult->penetrated,
                isMaxDamage: $maxDamageProc->triggered
            );
        }

        return new AttackResult($finalDamageInt, $isCritical, false, false, $damageType, false, $maxDamageProc->triggered);
    }

    private function checkDodge(Character $defender): bool
    {
        $result = $this->dodgePRNG->rollWithPRNG($defender->calculateDodgeChance(), $defender->getDodgeFailStreak());
        if ($result->success) {
            $defender->resetDodgeFailStreak();
        } else {
            $defender->incrementDodgeFailStreak();
        }
        return $result->success;
    }

    private function checkCritical(Character $attacker): \App\Domain\Battle\PseudoRandom\PRNGResult
    {
        $result = $this->critPRNG->rollWithPRNG($attacker->calculateCritChance(), $attacker->getCritFailStreak());
        if ($result->success) {
            $attacker->resetCritFailStreak();
        } else {
            $attacker->incrementCritFailStreak();
        }
        return $result;
    }
}
