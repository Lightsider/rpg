<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Application\Contracts\EventDispatcherInterface;

class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}

