<?php

declare(strict_types=1);

namespace App\Domain\Battle\PseudoRandom;

/**
 * Configuration for the Pseudo-Random Number Generator (PRNG) system.
 *
 * This provides dynamic chance adjustment (pity/anti-pity) for combat mechanics.
 * Formula: currentChance = baseChance * (1 + failStreak * upBonusFactor - successStreak * downPenaltyFactor)
 */
class PseudoRandomConfig
{
    public function __construct(
        public readonly int $k = 150,
        public readonly float $maxFinalChance = 0.95,
        public readonly float $upBonusFactor = 0.1, // +10% of base chance per failure
        public readonly float $downPenaltyFactor = 0.05, // -5% of base chance per success
        public readonly float $prngScale = 0.25, // Legacy support if needed
        public readonly bool $debug = false,
    ) {
    }

    /**
     * Create config from Laravel array (e.g., from config/combat.php).
     */
    public static function fromArray(array $config): self
    {
        return new self(
            k: $config['k'] ?? 150,
            maxFinalChance: $config['max_final_chance'] ?? 0.95,
            upBonusFactor: $config['up_bonus_factor'] ?? 0.1,
            downPenaltyFactor: $config['down_penalty_factor'] ?? 0.05,
            prngScale: $config['prng_scale'] ?? 0.25,
            debug: $config['debug'] ?? false,
        );
    }
}
