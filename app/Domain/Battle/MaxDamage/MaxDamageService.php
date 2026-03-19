<?php

declare(strict_types=1);

namespace App\Domain\Battle\MaxDamage;

use App\Domain\Battle\BlockPenetration\RatingConverter;
use App\Domain\Battle\Rng\DefaultRandomGenerator;
use App\Domain\Battle\Rng\RandomGeneratorInterface;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Log;

/**
 * Stateful domain service that resolves "Max Damage" proc attempts.
 *
 * State kept:
 *   - attacker_id > failed attempt counter
 *
 * Logic is similar to BlockPenetrationService but per-attacker (not per-pair).
 */
class MaxDamageService
{
    /**
     * @var array<int, int> attacker_id > failed attempt counter
     */
    private array $failedAttempts = [];

    private RandomGeneratorInterface $rng;

    public function __construct(
        private readonly MaxDamageConfig $config,
        ?RandomGeneratorInterface $rng = null,
    ) {
        $this->rng = $rng ?? new DefaultRandomGenerator();
    }

    /**
     * Perform a max damage proc roll.
     */
    public function checkMaxDamage(Character $attacker): MaxDamageResult
    {
        $rating = $attacker->getWeapon()->getMaxDamageRating();

        // If the weapon has no max damage rating, it can never trigger.
        if ($rating <= 0) {
            return new MaxDamageResult(triggered: false);
        }

        $baseChance = RatingConverter::toChance($rating, $this->config->k);
        $failures = $this->failedAttempts[$attacker->getId()] ?? 0;

        $finalChance = min(
            $this->config->maxFinalChance,
            $baseChance + $failures * ($baseChance * $this->config->prngScale)
        );

        $roll = $this->getRandom();
        $triggered = $roll < $finalChance;

        if ($triggered) {
            $this->resetCounter($attacker->getId());
        } else {
            $this->incrementCounter($attacker->getId());
        }

        if ($this->config->debug) {
            Log::debug('MaxDamageCheck', [
                'attacker_id' => $attacker->getId(),
                'rating' => $rating,
                'base_chance' => $baseChance,
                'failures' => $failures,
                'final_chance' => $finalChance,
                'roll' => $roll,
                'triggered' => $triggered,
            ]);
        }

        return new MaxDamageResult(
            triggered: $triggered,
            baseChance: $this->config->debug ? $baseChance : null,
            finalChance: $this->config->debug ? $finalChance : null,
            randomRoll: $this->config->debug ? $roll : null,
        );
    }

    public function resetCounter(int $attackerId): void
    {
        unset($this->failedAttempts[$attackerId]);
    }

    public function resetAllCounters(): void
    {
        $this->failedAttempts = [];
    }

    public function setRandomGenerator(RandomGeneratorInterface $rng): void
    {
        $this->rng = $rng;
    }

    private function incrementCounter(int $attackerId): void
    {
        $this->failedAttempts[$attackerId] = ($this->failedAttempts[$attackerId] ?? 0) + 1;
    }

    /**
     * Isolated for testability.
     */
    protected function getRandom(): float
    {
        return $this->rng->nextFloat();
    }
}
