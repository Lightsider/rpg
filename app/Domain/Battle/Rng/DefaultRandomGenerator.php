<?php

declare(strict_types=1);

namespace App\Domain\Battle\Rng;

class DefaultRandomGenerator implements RandomGeneratorInterface
{
    public function nextFloat(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
