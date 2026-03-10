<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

/**
 * Stateless utility that converts a rating difference into a probability.
 *
 * Formula: chance = effectiveRating / (effectiveRating + K)
 *
 * This service is intentionally generic so it can be reused for dodge rating,
 * critical hit rating, parry, status effects, etc.
 */
final class RatingConverter
{
    /**
     * Convert an effective (non-negative) rating into a [0.0, 1.0] probability.
     *
     * @param int $effectiveRating  max(0, attacker_rating - defender_rating)
     * @param int $k                denominator constant (higher → softer curve)
     */
    public static function toChance(int $effectiveRating, int $k): float
    {
        if ($effectiveRating <= 0) {
            return 0.0;
        }

        return min(1.0, $effectiveRating / ($effectiveRating + $k));
    }
}
