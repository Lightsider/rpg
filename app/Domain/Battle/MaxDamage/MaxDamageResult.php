<?php

declare(strict_types=1);

namespace App\Domain\Battle\MaxDamage;

/**
 * Result of a Max Damage proc check.
 */
class MaxDamageResult
{
    public function __construct(
        public readonly bool $triggered,
        public readonly ?float $baseChance = null,
        public readonly ?float $finalChance = null,
        public readonly ?float $randomRoll = null,
    ) {
    }
}
