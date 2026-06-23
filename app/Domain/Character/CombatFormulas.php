<?php

declare(strict_types=1);

namespace App\Domain\Character;

/**
 * Centralized combat/stat formulas for character-derived calculations.
 */
class CombatFormulas
{
    private const float DODGE_CHANCE_PER_AGILITY = 0.07;
    private const float CRIT_CHANCE_PER_WIT = 0.05;
    private const float CRIT_FLAT_BONUS_PER_WIT = 0.4;
    private const float STRENGTH_BONUS_MULTIPLIER = 0.42;

    /**
     * unused for now
    */
    private const float BASE_CRIT_MULTIPLIER = 1.5;
    private const float CRIT_MULTIPLIER_PER_WIT = 0.2;

    public static function dodgeChance(int $agility, int $level = 1): float
    {
        $levelMultiplier = 1.0 + (($level - 1) * 0.5);
        return $agility * (self::DODGE_CHANCE_PER_AGILITY / $levelMultiplier);
    }

    public static function critChance(int $wit, int $level = 1): float
    {
        $levelMultiplier = 1.0 + (($level - 1) * 0.5);
        return $wit * (self::CRIT_CHANCE_PER_WIT / $levelMultiplier);
    }

    public static function critFlatBonus(int $wit): float
    {
        return $wit * self::CRIT_FLAT_BONUS_PER_WIT;
    }

    public static function strengthBonus(int $strength): float
    {
        return $strength * self::STRENGTH_BONUS_MULTIPLIER;
    }

    /**
     * unused for now
    */
    public static function critMultiplier(int $wit): float
    {
        return self::BASE_CRIT_MULTIPLIER + ($wit * self::CRIT_MULTIPLIER_PER_WIT);
    }
}
