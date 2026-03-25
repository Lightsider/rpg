<?php

declare(strict_types=1);

namespace App\Domain\Battle\Rng;

interface RandomGeneratorInterface
{
    /**
     * @return float Random float in [0, 1)
     */
    public function nextFloat(): float;
}
