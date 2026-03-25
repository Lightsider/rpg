<?php

declare(strict_types=1);

namespace App\Domain\Battle\MaxDamage;

/**
 * Configuration for the Max Damage (Critical-like) mechanic.
 */
class MaxDamageConfig
{
    public function __construct(
        public readonly int $k = 300,
        public readonly float $maxFinalChance = 0.80,
        public readonly float $prngScale = 0.2,
        public readonly bool $debug = false,
    ) {
    }
}
