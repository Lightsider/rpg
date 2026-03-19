<?php

declare(strict_types=1);

namespace App\Domain\Battle\Rng;

/**
 * Deterministic RNG based on a simple 32-bit LCG.
 */
class DeterministicRandomGenerator implements RandomGeneratorInterface
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0x7fffffff;
    }

    public function nextFloat(): float
    {
        // LCG parameters from Numerical Recipes
        $this->state = (int) ((1664525 * $this->state + 1013904223) & 0x7fffffff);
        return $this->state / 0x80000000;
    }
}
