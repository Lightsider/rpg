<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

/**
 * Immutable result of a block penetration roll.
 *
 * The debug fields are populated only when
 * config('combat.block_penetration.debug') is true.
 */
final class BlockPenetrationResult
{
    public function __construct(
        public readonly bool $penetrated,
        public readonly int $damage,
        public readonly ?float $attackRating = null,
        public readonly ?float $defenseRating = null,
        public readonly ?float $effectiveRating = null,
        public readonly ?float $baseChance = null,
        public readonly ?float $finalChance = null,
        public readonly ?float $randomRoll = null,
    ) {
    }
}
