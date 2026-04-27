<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}

