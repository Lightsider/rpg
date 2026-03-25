<?php

declare(strict_types=1);

namespace App\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Log;

/**
 * Stateless service to resolve combat attacks.
 * Uses high-precision calculations (floats with rounding) and Fractional Damage Accumulation.
 * Integrates Zone-specific AD Armor (50% reduction).
 */
class CombatResolver
{
    private readonly ?PseudoRandomService $dodgePRNG;
    private readonly ?PseudoRandomService $critPRNG;
    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly BlockPenetrationService $blockPenetrationService,
        private readonly MaxDamageService $maxDamageService,
        ?PseudoRandomService $dodgePRNG = null,
        ?PseudoRandomService $critPRNG = null,
        ?RandomGeneratorInterface $rng = null,
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();

        if ($dodgePRNG === null) {
            $dodgePRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(k: 150, maxFinalChance: 0.80, prngScale: 0.25),
                $this->rng
            );
        }
        if ($critPRNG === null) {
            $critPRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(k: 150, maxFinalChance: 0.80, prngScale: 0.25),
                $this->rng
            );
        }
        $this->dodgePRNG = $dodgePRNG;
        $this->critPRNG = $critPRNG;
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
        $this->dodgePRNG->setRandomGenerator($rng);
        $this->critPRNG->setRandomGenerator($rng);
    }

    public function resolveAttack(Character $attacker, Character $defender, bool $isBlocked, ?TargetZone $targetZone = null): AttackResult
    {
        $weapon = $attacker->getWeaponForCombat();
        $damageType = $weapon->getDamageType();

        // 1. Check dodge
        if ($this->checkDodge($defender)) {
            return new AttackResult(0, false, true, false, $damageType);
        }

        // 2. Base Damage (Weapon + Seals + Strength)
        $maxDamageProc = $this->maxDamageService->checkMaxDamage($attacker);
        $weaponDamage = (float)($maxDamageProc->triggered ? $weapon->getMaxDamage() : $weapon->rollBaseDamage($this->rng));
        
        $currentDamage = $weaponDamage;
        $currentDamage += $attacker->getSealsBaseDamage($this->rng);
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

        // 4. Hit Zone Selection
        $hitZone = $targetZone ? $targetZone->value : $this->selectHitZone();

        // 5. Armor Reduction (50% to AD, 50% to HP)
        $finalDamageInt = $this->applyArmorReduction($defender, $hitZone, $currentDamage, $attacker);

        // 6. Block handling (Applied AFTER armor reduction? Typically block is total mitigation/penetration)
        // In this system, weapon block penetration deals 'damage' which is already calculated.
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

    protected function selectHitZone(): string
    {
        $roll = (int) ($this->rng->nextFloat() * 100) + 1;
        if ($roll <= 15) return TargetZone::HEAD->value;
        if ($roll <= 60) return TargetZone::TORSO->value;
        if ($roll <= 80) return TargetZone::LEGS->value;
        return (mt_rand(0, 1) === 0) ? TargetZone::LEFT_ARM->value : TargetZone::RIGHT_ARM->value;
    }

    private function applyArmorReduction(Character $defender, string $zone, float $incomingDamage, Character $attacker): int
    {
        $currentAd = $defender->getAdArmorForZone($zone);
        
        if ($currentAd <= 0) {
            return $this->applyFractionalAccumulation($attacker, $incomingDamage);
        }

        // 50% split
        $halfDamage = round($incomingDamage * 0.5, 4);
        
        // AD takes half
        $adDamage = $halfDamage;
        $hpDamage = $halfDamage;

        if ($currentAd < $adDamage) {
            $overflow = $adDamage - $currentAd;
            $hpDamage += $overflow;
            $defender->setAdArmorForZone($zone, 0.0);
        } else {
            $defender->setAdArmorForZone($zone, $currentAd - $adDamage);
        }

        return $this->applyFractionalAccumulation($attacker, $hpDamage);
    }

    private function applyFractionalAccumulation(Character $attacker, float $damage): int
    {
        $totalWithAccumulator = round($damage + $attacker->getDamageAccumulator(), 4);
        $finalDamageInt = (int) floor($totalWithAccumulator);
        $remainder = round($totalWithAccumulator - $finalDamageInt, 4);
        $attacker->setDamageAccumulator($remainder);

        return $finalDamageInt;
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
