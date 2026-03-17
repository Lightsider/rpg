<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets;

interface MessageHandlerInterface
{
    /**
     * @param array $message Raw payload that was parsed from JSON
     */
    public function handle(array $message): void;
}
