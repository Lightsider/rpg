<?php

declare(strict_types=1);

namespace App\Domain\Battle\PseudoRandom;

/**
 * Configuration for the Pseudo-Random Number Generator (PRNG) system.
 *
 * This provides bad-luck protection for combat mechanics by increasing
 * the probability of success after consecutive failures.
 */
class PseudoRandomConfig
{
    public function __construct(
        public readonly int $k = 150,
        public readonly float $maxFinalChance = 0.80,
        public readonly float $prngScale = 0.25,
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
            maxFinalChance: $config['max_final_chance'] ?? 0.80,
            prngScale: $config['prng_scale'] ?? 0.25,
            debug: $config['debug'] ?? false,
        );
    }
}
