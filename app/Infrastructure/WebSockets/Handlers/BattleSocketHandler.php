<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets\Handlers;

use App\Infrastructure\WebSockets\MessageHandlerInterface;
use App\Application\Battle\BattleService;
use Exception;

class BattleSocketHandler implements MessageHandlerInterface
{
    private BattleService $battleService;

    public function __construct(BattleService $battleService)
    {
        $this->battleService = $battleService;
    }

    public function handle(array $message): void
    {
        $payload = $message['payload'] ?? null;
        if (!$payload) {
            throw new Exception("Missing payload for battle handler");
        }

        $type = $message['type'];
        
        switch ($type) {
            case 'joinBattle':
                $this->battleService->joinBattle($message);
                break;
            case 'submitTurn':
                $this->battleService->submitTurn($message);
                break;
            default:
                throw new Exception("Unhandled battle message type: " . $type);
        }
    }
}
