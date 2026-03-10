<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

/**
 * Immutable configuration value object for the block penetration subsystem.
 *
 * K  – the denominator constant in chance = rating / (rating + K).
 *      Higher K means more rating is needed to reach a given probability.
 *
 * maxFinalChance – absolute ceiling after bad-luck bonus is applied (0.95).
 *
 * prngScale – multiplier applied to baseChance per consecutive failed attempt
 *              to provide bad-luck protection.
 */
final class BlockPenetrationConfig
{
    public function __construct(
        public readonly int $k = 120,
        public readonly float $maxFinalChance = 0.95,
        public readonly float $prngScale = 0.3,
        public readonly bool $debug = false,
    ) {
    }
}
