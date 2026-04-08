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
use App\Domain\Weapon\Dagger;
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
    private readonly ?PseudoRandomService $parryPRNG;
    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly BlockPenetrationService $blockPenetrationService,
        private readonly MaxDamageService $maxDamageService,
        ?PseudoRandomService $dodgePRNG = null,
        ?PseudoRandomService $critPRNG = null,
        ?PseudoRandomService $parryPRNG = null,
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
        if ($parryPRNG === null) {
            $parryPRNG = new PseudoRandomService(
                new \App\Domain\Battle\PseudoRandom\PseudoRandomConfig(k: 120, maxFinalChance: 0.85, prngScale: 0.15),
                $this->rng
            );
        }
        $this->dodgePRNG = $dodgePRNG;
        $this->critPRNG = $critPRNG;
        $this->parryPRNG = $parryPRNG;
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
        $this->dodgePRNG->setRandomGenerator($rng);
        $this->critPRNG->setRandomGenerator($rng);
        $this->parryPRNG->setRandomGenerator($rng);
    }

    public function resolveAttack(
        Character $attacker,
        Character $defender,
        bool $isBlocked,
        ?TargetZone $targetZone = null,
        ?\App\Domain\Weapon\Weapon $forcedWeapon = null
    ): AttackResult {
        $weapon = $forcedWeapon ?? $attacker->getWeaponForCombat();
        $damageType = $weapon->getDamageType();

        // 0. Hit Zone Selection (Moved up to support zone-based dodge)
        $selectedZone = $targetZone ?? TargetZone::from($this->selectHitZone());
        $hitZoneValue = $selectedZone->value;

        // 1. Check dodge (Now zone-specific)
        if ($this->checkDodge($defender, $selectedZone)) {
            return new AttackResult(0, false, true, false, $damageType);
        }

        // 2. Check parry (Dagger mechanic)
        if ($this->checkParry($defender, $weapon)) {
            return new AttackResult(0, false, false, false, $damageType, false, false, true);
        }

        // 3. Base Damage (Weapon + Seals + Strength)
        $maxDamageProc = null;
        if (!method_exists($weapon, 'isMaxDamageEnabled') || $weapon->isMaxDamageEnabled()) {
            $maxDamageProc = $this->maxDamageService->checkMaxDamage($attacker);
        }
        
        $weaponDamage = (float)(($maxDamageProc && $maxDamageProc->triggered) ? $weapon->getMaxDamage() : $weapon->rollBaseDamage($this->rng));
        
        $currentDamage = $weaponDamage;
        $currentDamage += $attacker->getSealsBaseDamage($this->rng);
        
        if (!($weapon instanceof Dagger)) {
            $currentDamage += $attacker->calculateStrengthBonus();
        }

        // 3. Critical Hit
        $isCritical = false;
        if ($this->checkCritical($attacker)->success) {
            $isCritical = true;
            $currentDamage += (float)$weapon->getFlatCritBonus();
            $currentDamage += $attacker->calculateCritFlatBonus();
            $currentDamage += $attacker->getSealsFlatCritBonus();
        }

        // Round to 4 decimal places for precision management
        $currentDamage = round($currentDamage, 4);

        // 5. Block handling
        $isPierced = false;
        if ($isBlocked) {
            $penetrationResult = $this->blockPenetrationService->checkBlockBreak($attacker, $defender, (int) round($currentDamage));
            
            if (!$penetrationResult->penetrated) {
                return new AttackResult(
                    damage: 0,
                    isCritical: $isCritical,
                    isDodged: false,
                    isMiss: false,
                    damageType: $damageType,
                    isPierced: false,
                    isMaxDamage: ($maxDamageProc && $maxDamageProc->triggered)
                );
            }
            
            $currentDamage = (float) $penetrationResult->damage;
            $isPierced = true;
        }

        // 6. Armor Reduction (50% to AD, 50% to HP)
        $finalDamageInt = $this->applyArmorReduction($defender, $hitZoneValue, $currentDamage, $attacker);

        $isMaxDamage = ($maxDamageProc && $maxDamageProc->triggered);

        return new AttackResult($finalDamageInt, $isCritical, false, false, $damageType, $isPierced, $isMaxDamage, false);
    }

    private function checkParry(Character $defender, \App\Domain\Weapon\Weapon $attackerWeapon): bool
    {
        $rating = $defender->getParryRating();
        if ($rating <= 0) {
            return false;
        }

        $baseChance = $defender->calculateParryChance();

        // 2H weapons are harder to parry (50% penalty)
        if ($attackerWeapon->isTwoHanded()) {
            $baseChance *= 0.5;
        }

        $result = $this->parryPRNG->rollWithPRNG(
            $baseChance,
            $defender->getParryFailStreak(),
            $defender->getParrySuccessStreak()
        );

        if ($result->success) {
            $defender->incrementParrySuccessStreak();
        } else {
            $defender->incrementParryFailStreak();
        }

        return $result->success;
    }

    protected function selectHitZone(): string
    {
        $roll = (int) ($this->rng->nextFloat() * 100) + 1;
        if ($roll <= 15) return TargetZone::HEAD->value;
        if ($roll <= 60) return TargetZone::TORSO->value;
        if ($roll <= 80) return TargetZone::LEGS->value;
        return ($this->rng->nextFloat() < 0.5) ? TargetZone::LEFT_ARM->value : TargetZone::RIGHT_ARM->value;
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

    private function checkDodge(Character $defender, TargetZone $zone): bool
    {
        $result = $this->dodgePRNG->rollWithPRNG(
            $defender->calculateDodgeChance($zone),
            $defender->getDodgeFailStreak(),
            $defender->getDodgeSuccessStreak()
        );
        
        if ($result->success) {
            $defender->recordDodgeSuccess();
        } else {
            $defender->recordDodgeFailure();
        }
        return $result->success;
    }

    private function checkCritical(Character $attacker): \App\Domain\Battle\PseudoRandom\PRNGResult
    {
        $result = $this->critPRNG->rollWithPRNG(
            $attacker->calculateCritChance(),
            $attacker->getCritFailStreak(),
            $attacker->getCritSuccessStreak()
        );
        
        if ($result->success) {
            $attacker->recordCritSuccess();
        } else {
            $attacker->recordCritFailure();
        }
        return $result;
    }
}
