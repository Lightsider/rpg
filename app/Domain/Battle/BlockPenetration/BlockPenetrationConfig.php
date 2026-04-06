<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

/**
 * Immutable configuration value object for the block penetration subsystem.
 */
final class BlockPenetrationConfig
{
    public function __construct(
        public readonly int $k = 120,
        public readonly float $minFinalChance = 0.05,
        public readonly float $maxFinalChance = 0.95,
        public readonly float $upBonusFactor = 0.1,
        public readonly float $downPenaltyFactor = 0.05,
        public readonly float $prngScale = 0.2, // Legacy
        public readonly bool $debug = false,
    ) {
    }
}
