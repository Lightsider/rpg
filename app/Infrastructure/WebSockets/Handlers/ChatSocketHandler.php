<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets\Handlers;

use App\Infrastructure\WebSockets\MessageHandlerInterface;

class ChatSocketHandler implements MessageHandlerInterface
{
    public function handle(array $message): void
    {
        // For future chat implementation
    }
}
