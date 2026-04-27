<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Application\Contracts\ClockInterface;

class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

