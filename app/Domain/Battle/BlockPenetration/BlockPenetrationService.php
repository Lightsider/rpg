<?php

declare(strict_types=1);

namespace App\Domain\Battle\BlockPenetration;

use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Character\Character;
use App\Domain\Weapon\Weapon;
use Illuminate\Support\Facades\Log;

/**
 * Stateful domain service that resolves block penetration attempts.
 *
 * State kept:
 *   - (attacker_id, defender_id) > failed attempt counter
 *   - (attacker_id) > last weapon id used (to detect weapon changes)
 *
 * All randomness is isolated to getRandom() so it can be overridden in tests.
 */
class BlockPenetrationService
{
    /**
     * @var array<string, int> key = "{attacker_id}:{defender_id}"
     */
    private array $failedAttempts = [];

    /**
     * @var array<int, int> attacker_id > last weapon id
     */
    private array $lastWeaponId = [];

    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly BlockPenetrationConfig $config,
        ?RandomGeneratorInterface $rng = null,
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Effective rating = max(0, attacker.block_break_rating - defender.block_resist_rating).
     */
    public function calculateEffectiveRating(Character $attacker, Character $defender): int
    {
        return max(0, $attacker->getWeapon()->getBlockBreakRating() - $defender->getBlockResistRating());
    }

    /**
     * Base penetration chance derived from effective rating via the configurable curve.
     * Does NOT include bad-luck scaling.
     */
    public function calculateBlockBreakChance(Character $attacker, Character $defender): float
    {
        $effectiveRating = $this->calculateEffectiveRating($attacker, $defender);
        return RatingConverter::toChance($effectiveRating, $this->config->k);
    }

    /**
     * Perform a full block-penetration roll.
     *
     * Applies:
     *  1. Bad-luck PRNG scaling for consecutive failures.
     *  2. Capped at config->maxFinalChance.
     *  3. Resets or increments the per-pair failure counter.
     *
     * @param int $baseDamage  The raw damage value before pierce reduction.
     */
    public function checkBlockBreak(
        Character $attacker,
        Character $defender,
        int $baseDamage,
    ): BlockPenetrationResult {
        $this->handleWeaponChange($attacker);

        $attackRating = $attacker->getWeapon()->getBlockBreakRating();
        $defenseRating = $defender->getBlockResistRating();
        $effectiveRating = max(0, $attackRating - $defenseRating);

        $baseChance = RatingConverter::toChance($effectiveRating, $this->config->k);
        $failures = $this->getFailures($attacker->getId(), $defender->getId());
        $finalChance = min(
            $this->config->maxFinalChance,
            $baseChance + $failures * ($baseChance * $this->config->prngScale),
        );

        $roll = $this->getRandom();
        $penetrated = $roll < $finalChance;

        $damage = $penetrated
            ? $this->applyPierceDamage($baseDamage, $attacker->getWeapon())
            : 0;

        if ($penetrated) {
            $this->resetCounter($attacker->getId(), $defender->getId());
        } else {
            $this->incrementCounter($attacker->getId(), $defender->getId());
        }

        if ($this->config->debug) {
            Log::debug('BlockPenetration', [
                'attacker_id' => $attacker->getId(),
                'defender_id' => $defender->getId(),
                'attack_rating' => $attackRating,
                'defense_rating' => $defenseRating,
                'effective_rating' => $effectiveRating,
                'base_chance' => $baseChance,
                'final_chance' => $finalChance,
                'random_roll' => $roll,
                'penetrated' => $penetrated,
                'damage' => $damage,
            ]);
        }

        return new BlockPenetrationResult(
            penetrated: $penetrated,
            damage: $damage,
            attackRating: $this->config->debug ? (float) $attackRating : null,
            defenseRating: $this->config->debug ? (float) $defenseRating : null,
            effectiveRating: $this->config->debug ? (float) $effectiveRating : null,
            baseChance: $this->config->debug ? $baseChance : null,
            finalChance: $this->config->debug ? $finalChance : null,
            randomRoll: $this->config->debug ? $roll : null,
        );
    }

    /**
     * Reduce base damage by the weapon's pierce multiplier.
     */
    public function applyPierceDamage(int $baseDamage, Weapon $weapon): int
    {
        return (int) round($baseDamage * $weapon->getPierceMultiplier());
    }

    /**
     * Explicitly reset the failure counter for a specific attacker-defender pair.
     * Call this when the attacker changes target.
     */
    public function resetCounter(int $attackerId, int $defenderId): void
    {
        unset($this->failedAttempts[$this->key($attackerId, $defenderId)]);
    }

    /**
     * Reset ALL counters. Call this when combat ends.
     */
    public function resetAllCounters(): void
    {
        $this->failedAttempts = [];
        $this->lastWeaponId = [];
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function getFailures(int $attackerId, int $defenderId): int
    {
        return $this->failedAttempts[$this->key($attackerId, $defenderId)] ?? 0;
    }

    private function incrementCounter(int $attackerId, int $defenderId): void
    {
        $key = $this->key($attackerId, $defenderId);
        $this->failedAttempts[$key] = ($this->failedAttempts[$key] ?? 0) + 1;
    }

    private function key(int $attackerId, int $defenderId): string
    {
        return "{$attackerId}:{$defenderId}";
    }

    /**
     * Detect weapon swap and reset the counter for that attacker against all targets.
     */
    private function handleWeaponChange(Character $attacker): void
    {
        $weaponId = $attacker->getWeapon()->getId();
        $last = $this->lastWeaponId[$attacker->getId()] ?? null;

        if ($last !== null && $last !== $weaponId) {
            // Weapon changed — invalidate all pairs for this attacker
            $prefix = "{$attacker->getId()}:";
            foreach (array_keys($this->failedAttempts) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset($this->failedAttempts[$key]);
                }
            }
        }

        $this->lastWeaponId[$attacker->getId()] = $weaponId;
    }

    /**
     * Isolated for testability.
     */
    protected function getRandom(): float
    {
        return $this->rng->nextFloat();
    }
}
