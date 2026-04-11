<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface RandomGeneratorInterface
{
    public function nextFloat(): float;
}
