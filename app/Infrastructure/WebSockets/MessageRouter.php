<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets;

class MessageRouter
{
    /** @var array<string, MessageHandlerInterface> */
    private array $handlers = [];

    public function registerHandler(string $type, MessageHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function dispatch(array $message): void
    {
        $type = $message['type'] ?? '';

        if (!isset($this->handlers[$type])) {
            $connection = $message['_connection'];
            $connection->send(json_encode([
                'type' => 'error',
                'payload' => ['message' => "No handler found for type: $type"],
                'timestamp' => time()
            ]));
            return;
        }

        try {
            $this->handlers[$type]->handle($message);
        } catch (\Throwable $e) {
            $connection = $message['_connection'];
            $connection->send(json_encode([
                'type' => 'error',
                'payload' => ['message' => 'Internal server error: ' . $e->getMessage()],
                'timestamp' => time()
            ]));
        }
    }
}
