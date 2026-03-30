<?php

declare(strict_types=1);

namespace App\Domain\Battle\MaxDamage;

use App\Domain\Battle\BlockPenetration\RatingConverter;
use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Log;

/**
 * Domain service that resolves "Max Damage" proc attempts.
 *
 * Uses the Character's internal streaks for dynamic chance adjustment.
 */
class MaxDamageService
{
    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly MaxDamageConfig $config,
        ?RandomGeneratorInterface $rng = null,
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();
    }

    /**
     * Perform a max damage proc roll.
     */
    public function checkMaxDamage(Character $attacker): MaxDamageResult
    {
        $rating = $attacker->getWeaponForCombat()->getMaxDamageRating();

        // If the weapon has no max damage rating, it can never trigger.
        if ($rating <= 0) {
            return new MaxDamageResult(triggered: false);
        }

        $baseChance = RatingConverter::toChance($rating, $this->config->k);
        $failures = $attacker->getMaxDamageFailStreak();
        $successes = $attacker->getMaxDamageSuccessStreak();

        // Calculate chance using the formula: base * (1 + fail * up - success * down)
        $modifier = 1.0 + ($failures * $this->config->upBonusFactor) - ($successes * $this->config->downPenaltyFactor);
        $finalChance = (float) max(0.0, min($this->config->maxFinalChance, $baseChance * $modifier));

        $roll = $this->getRandom();
        $triggered = $roll < $finalChance;

        if ($triggered) {
            $attacker->recordMaxDamageSuccess();
        } else {
            $attacker->recordMaxDamageFailure();
        }

        if ($this->config->debug) {
            Log::debug('MaxDamageCheck', [
                'attacker_id' => $attacker->getId(),
                'rating' => $rating,
                'base_chance' => $baseChance,
                'failures' => $failures,
                'successes' => $successes,
                'final_chance' => $finalChance,
                'roll' => $roll,
                'triggered' => $triggered,
            ]);
        }

        return new MaxDamageResult(
            triggered: $triggered,
            baseChance: $this->config->debug ? $baseChance : null,
            finalChance: $this->config->debug ? $finalChance : null,
            randomRoll: $this->config->debug ? $roll : null,
        );
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
