<?php

declare(strict_types=1);

namespace App\Domain\Battle\PseudoRandom;

use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use Illuminate\Support\Facades\Log;

/**
 * Reusable service for Pseudo-Random Number Generation (PRNG) with pity and anti-pity protection.
 *
 * This system increases the probability of success after consecutive failures,
 * and decreases it after consecutive successes, making combat results statistically stable.
 *
 * Formula:
 *   currentChance = baseChance * (1 + failStreak * upBonusFactor - successStreak * downPenaltyFactor)
 *   capped at maxFinalChance.
 */
class PseudoRandomService
{
    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly PseudoRandomConfig $config,
        ?RandomGeneratorInterface $rng = null
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();
    }

    /**
     * Perform a roll with dynamic chance scaling.
     *
     * @param float $baseChance The base probability (0.0 to 1.0)
     * @param int $failureCount Number of consecutive failed attempts
     * @param int $successCount Number of consecutive successful attempts
     * @return PRNGResult Contains success status and debug information
     */
    public function rollWithPRNG(float $baseChance, int $failureCount, int $successCount = 0): PRNGResult
    {
        $finalChance = $this->calculateFinalChance($baseChance, $failureCount, $successCount);

        $roll = $this->getRandom();
        $success = $roll < $finalChance;

        if ($this->config->debug) {
            Log::debug('PRNGRoll', [
                'base_chance' => $baseChance,
                'failures' => $failureCount,
                'successes' => $successCount,
                'final_chance' => $finalChance,
                'roll' => $roll,
                'success' => $success,
            ]);
        }

        return new PRNGResult(
            success: $success,
            baseChance: $this->config->debug ? $baseChance : null,
            finalChance: $this->config->debug ? $finalChance : null,
            randomRoll: $this->config->debug ? $roll : null,
        );
    }

    /**
     * Calculate the final chance after applying pity/anti-pity.
     *
     * Formula: finalChance = baseChance * (1 + failures * upBonus - successes * downPenalty)
     */
    public function calculateFinalChance(float $baseChance, int $failureCount, int $successCount): float
    {
        $modifier = 1.0 + ($failureCount * $this->config->upBonusFactor) - ($successCount * $this->config->downPenaltyFactor);
        
        $finalChance = $baseChance * $modifier;
        
        // Ensure final chance is between 0 and maxFinalChance
        return (float) max(0.0, min($this->config->maxFinalChance, $finalChance));
    }

    /**
     * Isolated for testability.
     */
    protected function getRandom(): float
    {
        return $this->rng->nextFloat();
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
    }
}
