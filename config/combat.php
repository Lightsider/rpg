<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Block Penetration Settings
    |--------------------------------------------------------------------------
    |
    | K controls the rating-to-probability curve for block penetration.
    | A higher K makes it harder to achieve high penetration chances even with
    | a big rating advantage.  Tune this as the game scales.
    |
    | Formula: chance = effective_rating / (effective_rating + K)
    |
    */
    'block_penetration' => [
        'k' => env('COMBAT_BLOCK_PENETRATION_K', 120),
        'max_final_chance' => 0.95,
        'prng_scale' => 0.3,   // bad-luck bonus per consecutive failed attempt
        'debug' => env('COMBAT_BLOCK_PENETRATION_DEBUG', false),
    ],

    'max_damage' => [
        'k' => env('COMBAT_MAX_DAMAGE_K', 300),
        'max_final_chance' => 0.80,
        'prng_scale' => 0.25,
        'debug' => env('COMBAT_MAX_DAMAGE_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dodge Settings
    |--------------------------------------------------------------------------
    |
    | K controls the rating-to-probability curve for dodge.
    | Higher K makes it harder to achieve high dodge chances.
    |
    | Formula: chance = dodge_rating / (dodge_rating + K)
    |
    */
    'dodge' => [
        'k' => env('COMBAT_DODGE_K', 150),
        'max_final_chance' => 0.80,
        'prng_scale' => 0.25,
        'debug' => env('COMBAT_DODGE_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical Hit Settings
    |--------------------------------------------------------------------------
    |
    | K controls the rating-to-probability curve for critical hits.
    | Higher K makes it harder to achieve high crit chances.
    |
    | Formula: chance = crit_rating / (crit_rating + K)
    |
    */
    'critical_hit' => [
        'k' => env('COMBAT_CRIT_K', 150),
        'max_final_chance' => 0.80,
        'prng_scale' => 0.25,
        'debug' => env('COMBAT_CRIT_DEBUG', false),
    ],
];
