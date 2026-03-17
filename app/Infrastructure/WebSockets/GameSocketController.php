<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets;

use Workerman\Connection\TcpConnection;

class GameSocketController
{
    private MessageRouter $router;
    
    /** @var array<int, TcpConnection> */
    private array $connections = [];

    public function __construct(MessageRouter $router)
    {
        $this->router = $router;
    }

    public function onConnect(TcpConnection $connection): void
    {
        $this->connections[$connection->id] = $connection;
        
        $connection->send(json_encode([
            'type' => 'connected',
            'payload' => [
                'connectionId' => $connection->id
            ],
            'timestamp' => time()
        ]));

        echo "Client connected: {$connection->id}\n";
    }

    public function onMessage(TcpConnection $connection, string $data): void
    {
        $decoded = json_decode($data, true);
        if (!$decoded || !isset($decoded['type'])) {
            $connection->send(json_encode([
                'type' => 'error',
                'payload' => ['message' => 'Invalid JSON or missing type'],
                'timestamp' => time()
            ]));
            return;
        }

        if ($decoded['type'] === 'ping') {
            $connection->send(json_encode([
                'type' => 'pong',
                'timestamp' => time()
            ]));
            return;
        }

        // Attach connection info to the decoded payload for the handler
        $decoded['_connection'] = $connection;
        $decoded['_gameSocket'] = $this;

        $this->router->dispatch($decoded);
    }

    public function onClose(TcpConnection $connection): void
    {
        unset($this->connections[$connection->id]);
        echo "Client disconnected: {$connection->id}\n";
    }

    public function onError(TcpConnection $connection, $code, $msg): void
    {
        echo "Error on connection {$connection->id}: $msg\n";
    }
    
    /**
     * @return array<int, TcpConnection>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }
}
