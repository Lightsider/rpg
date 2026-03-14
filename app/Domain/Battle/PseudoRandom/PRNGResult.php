<?php

declare(strict_types=1);

namespace App\Domain\Battle\PseudoRandom;

/**
 * Result of a PRNG roll with bad-luck protection.
 */
class PRNGResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?float $baseChance = null,
        public readonly ?float $finalChance = null,
        public readonly ?float $randomRoll = null,
    ) {
    }
}
