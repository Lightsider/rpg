<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Character\Character;
use App\Domain\Weapon\Weapon;
use Illuminate\Support\Facades\Log;

/**
 * Domain service that resolves block penetration attempts.
 *
 * Now uses Global streaks (stored in Character) for dynamic chance adjustment.
 * Removed per-target-pair tracking to provide a more consistent "pity" experience.
 */
class BlockPenetrationService
{
    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly BlockPenetrationConfig $config,
        ?RandomGeneratorInterface $rng = null,
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();
    }

    /**
     * Effective rating = max(0, attacker.block_break_rating - defender.block_resist_rating).
     */
    public function calculateEffectiveRating(Character $attacker, Character $defender): int
    {
        return max(0, $attacker->getWeaponForCombat()->getBlockBreakRating() - $defender->getBlockResistRating());
    }

    /**
     * Perform a full block-penetration roll.
     */
    public function checkBlockBreak(
        Character $attacker,
        Character $defender,
        int $baseDamage,
    ): BlockPenetrationResult {
        $attackRating = $attacker->getWeaponForCombat()->getBlockBreakRating();
        $defenseRating = $defender->getBlockResistRating();
        $effectiveRating = max(0, $attackRating - $defenseRating);

        $baseChance = RatingConverter::toChance($effectiveRating, $this->config->k);
        
        $failures = $attacker->getPenetrationFailStreak();
        $successes = $attacker->getPenetrationSuccessStreak();

        // Calculate chance using the formula: base * (1 + fail * up - success * down)
        $modifier = 1.0 + ($failures * $this->config->upBonusFactor) - ($successes * $this->config->downPenaltyFactor);
        $finalChance = (float) max(0.0, min($this->config->maxFinalChance, $baseChance * $modifier));

        $roll = $this->getRandom();
        $penetrated = $roll < $finalChance;

        $damage = $penetrated
            ? $this->applyPierceDamage($baseDamage, $attacker->getWeaponForCombat())
            : 0;

        if ($penetrated) {
            $attacker->recordPenetrationSuccess();
        } else {
            $attacker->recordPenetrationFailure();
        }

        if ($this->config->debug) {
            Log::debug('BlockPenetration', [
                'attacker_id' => $attacker->getId(),
                'defender_id' => $defender->getId(),
                'attack_rating' => $attackRating,
                'defense_rating' => $defenseRating,
                'effective_rating' => $effectiveRating,
                'base_chance' => $baseChance,
                'failures' => $failures,
                'successes' => $successes,
                'final_chance' => $finalChance,
                'random_roll' => $roll,
                'penetrated' => $penetrated,
                'damage' => $damage,
            ]);
        }

        return new BlockPenetrationResult(
            penetrated: $penetrated,
            damage: $damage,
            attackRating: $this->config->debug ? (float) $attackRating : null,
            defenseRating: $this->config->debug ? (float) $defenseRating : null,
            effectiveRating: $this->config->debug ? (float) $effectiveRating : null,
            baseChance: $this->config->debug ? $baseChance : null,
            finalChance: $this->config->debug ? $finalChance : null,
            randomRoll: $this->config->debug ? $roll : null,
        );
    }

    /**
     * Reduce base damage by the weapon's pierce multiplier.
     */
    public function applyPierceDamage(int $baseDamage, Weapon $weapon): int
    {
        return (int) round($baseDamage * $weapon->getPierceMultiplier());
    }

    /**
     * Reset ALL streaks for a character is handled by Character::resetAllStreaks.
     */
    public function resetCharacterStreaks(Character $character): void
    {
        // Handled by Character model
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
    }

    /**
     * Isolated for testability.
     */
    protected function getRandom(): float
    {
        return $this->rng->nextFloat();
    }
}
