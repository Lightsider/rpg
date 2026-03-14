<?php

declare(strict_types=1);

namespace App\Domain\Battle\PseudoRandom;

use Illuminate\Support\Facades\Log;

/**
 * Reusable service for Pseudo-Random Number Generation (PRNG) with bad-luck protection.
 *
 * This system increases the probability of success after consecutive failures,
 * reducing streaks of bad luck and making combat results statistically stable.
 *
 * Formula:
 *   baseChance = rating / (rating + K)
 *   finalChance = min(maxFinalChance, baseChance + failures * (baseChance * prngScale))
 */
class PseudoRandomService
{
    public function __construct(
        private readonly PseudoRandomConfig $config,
    ) {
    }

    /**
     * Perform a roll with PRNG bad-luck protection.
     *
     * @param float $baseChance The base probability (0.0 to 1.0)
     * @param int $failureCount Number of consecutive failed attempts
     * @return PRNGResult Contains success status and debug information
     */
    public function rollWithPRNG(float $baseChance, int $failureCount): PRNGResult
    {
        $finalChance = $this->calculateFinalChance($baseChance, $failureCount);

        $roll = $this->getRandom();
        $success = $roll < $finalChance;

        if ($this->config->debug) {
            Log::debug('PRNGRoll', [
                'base_chance' => $baseChance,
                'failures' => $failureCount,
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
     * Calculate the final chance after applying bad-luck protection.
     *
     * Formula: finalChance = min(maxFinalChance, baseChance + failures * (baseChance * prngScale))
     */
    public function calculateFinalChance(float $baseChance, int $failureCount): float
    {
        $scaledIncrease = $baseChance * $failureCount * $this->config->prngScale;
        return min($this->config->maxFinalChance, $baseChance + $scaledIncrease);
    }

    /**
     * Isolated for testability.
     */
    protected function getRandom(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
