<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Infrastructure\WebSockets\GameSocketController;
use App\Events\Battle\BattleUpdated;
use App\Events\Battle\BattleEnded;
use App\Events\Battle\RoundStarted;
use App\Events\Battle\BattleJoined;
use App\Domain\Battle\BattleState;
use App\Domain\DomainException;
use Illuminate\Support\Facades\Event;
use Workerman\Connection\TcpConnection;

class BattleService
{
    /** @var array<int, array<int, TcpConnection>> [battleId => [connId => conn]] */
    private array $rooms = [];

    /** @var array<int, array{characterId: int, battleId: int}> [connectionId => metadata] */
    private array $connectionMeta = [];

    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly QueueAttackAction $queueAttackAction,
        private readonly QueueDefenseAction $queueDefenseAction,
        private readonly QueueMoveAction $queueMoveAction,
        private readonly CommitRoundAction $commitRoundAction
    ) {
        // Register listeners to forward domain events to WebSocket clients
        Event::listen(BattleJoined::class, function (BattleJoined $event) {
            $this->broadcastToRoom($event->battle->getId(), [
                'type' => 'battleJoined',
                'payload' => $this->buildFullState($event->battle),
                'timestamp' => time()
            ]);
        });

        Event::listen(RoundStarted::class, function (RoundStarted $event) {
            $this->broadcastToRoom($event->battleId, [
                'type' => 'roundStarted',
                'payload' => [
                    'round' => $event->round,
                    'timeout' => $event->timeout
                ],
                'timestamp' => time()
            ]);
        });
        
        Event::listen(BattleUpdated::class, function (BattleUpdated $event) {
            $this->broadcastToRoom($event->battle->getId(), [
                'type' => 'battleUpdate',
                'payload' => [
                    'round' => $event->battle->getRoundNumber(),
                    'events' => $event->payload['events'],
                    'players' => array_map(fn($p) => [
                        'character_id' => $p->getId(),
                        'hp' => $p->getCurrentHp(),
                        'max_hp' => $p->getMaxHp(),
                        'x' => $p->getX(),
                        'y' => $p->getY(),
                    ], array_values($event->battle->getParticipants())),
                ],
                'timestamp' => time()
            ]);
        });

        Event::listen(BattleEnded::class, function (BattleEnded $event) {
            $this->broadcastToRoom($event->battle->getId(), [
                'type' => 'battleEnded',
                'payload' => [
                    'winnerId' => $event->payload['winnerId'],
                    'reason' => $event->payload['reason'],
                    'round' => $event->battle->getRoundNumber()
                ],
                'timestamp' => time()
            ]);
        });
    }

    public function joinBattle(array $message): void
    {
        $payload = $message['payload'];
        $battleId = (int) ($payload['battleId'] ?? 0);
        $characterId = (int) ($payload['characterId'] ?? 0);
        
        /** @var TcpConnection $connection */
        $connection = $message['_connection'];

        if (!$battleId || !$characterId) {
            $this->sendError($connection, "Missing battleId or characterId.");
            return;
        }

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            $this->sendError($connection, "Invalid battleId.");
            return;
        }

        if (!$battle->getParticipantById($characterId)) {
            $this->sendError($connection, "Character is not a participant.");
            return;
        }

        // STEP 10 - Handle reconnects
        if (isset($this->rooms[$battleId])) {
            foreach ($this->rooms[$battleId] as $cId => $conn) {
                if (isset($this->connectionMeta[$cId]) && $this->connectionMeta[$cId]['characterId'] === $characterId && $conn !== $connection) {
                    $this->sendError($conn, "Another connection established.");
                    $conn->close();
                    unset($this->rooms[$battleId][$cId]);
                    unset($this->connectionMeta[$cId]);
                }
            }
        }

        // Keep track of connection
        $this->connectionMeta[$connection->id] = [
            'characterId' => $characterId,
            'battleId' => $battleId
        ];
        $this->rooms[$battleId][$connection->id] = $connection;

        $connection->send(json_encode([
            'type' => 'battleState',
            'payload' => $this->buildFullState($battle),
            'timestamp' => time()
        ]));
    }

    public function submitTurn(array $message): void
    {
        $payload = $message['payload'];
        $battleId = (int) ($payload['battleId'] ?? 0);
        $actions = $payload['actions'] ?? [];
        
        /** @var TcpConnection $connection */
        $connection = $message['_connection'];
        
        $meta = $this->connectionMeta[$connection->id] ?? null;
        $characterId = $meta ? $meta['characterId'] : (int) ($payload['characterId'] ?? 0);
        $submittedRound = (int) ($payload['round'] ?? 0);

        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            $this->sendError($connection, "Invalid battleId.");
            return;
        }

        if ($battle->getRoundNumber() !== $submittedRound) {
            $this->sendError($connection, "Outdated round submission.");
            return;
        }

        if ($battle->isCharacterCommitted($characterId)) {
            $this->sendError($connection, "Turn already submitted.");
            return;
        }

        try {
            foreach ($actions as $actionData) {
                $type = $actionData['type'];
                $zone = $actionData['zone'] ?? null;
                if ($zone === 'body') {
                    $zone = 'torso';
                }

                if ($type === 'attack') {
                    $this->queueAttackAction->execute($battleId, $characterId, $zone);
                } elseif ($type === 'block') {
                    $this->queueDefenseAction->execute($battleId, $characterId, $zone);
                } elseif ($type === 'move') {
                    $target = $actionData['target'];
                    $blocks = $actionData['blocks'] ?? [];
                    $this->queueMoveAction->execute($battleId, $characterId, (int) $target['x'], (int) $target['y'], $blocks);
                }
            }

            $this->commitRoundAction->execute($battleId, $characterId);

            $connection->send(json_encode([
                'type' => 'turnSubmitted',
                'payload' => ['round' => $submittedRound],
                'timestamp' => time()
            ]));

        } catch (DomainException $e) {
            $this->sendError($connection, $e->getMessage());
        } catch (\Exception $e) {
            $this->sendError($connection, "Failed to submit actions.");
        }
    }

    private function broadcastToRoom(int $battleId, array $message): void
    {
        if (!isset($this->rooms[$battleId])) {
            return;
        }

        $encoded = json_encode($message);
        foreach ($this->rooms[$battleId] as $connId => $connection) {
            $connection->send($encoded);
        }
    }

    private function sendError(TcpConnection $connection, string $error): void
    {
        $connection->send(json_encode([
            'type' => 'error',
            'payload' => ['message' => $error],
            'timestamp' => time()
        ]));
    }

    private function buildFullState($battle): array
    {
        return [
            'id' => $battle->getId(),
            'status' => $battle->getState()->value,
            'round' => $battle->getRoundNumber(),
            'map' => [
                'width' => $battle->getMap()->getWidth(),
                'height' => $battle->getMap()->getHeight(),
            ],
            'participants' => array_map(fn($p) => [
                'character_id' => $p->getId(),
                'name' => $p->getName(),
                'hp' => $p->getCurrentHp(),
                'max_hp' => $p->getMaxHp()
            ], array_values($battle->getParticipants())),
            'positions' => array_map(fn($p) => [
                'character_id' => $p->getId(),
                'x' => $p->getX(),
                'y' => $p->getY(),
            ], array_values($battle->getParticipants())),
            'actions_submitted' => $battle->getCommittedCharacterIds(),
            'timer_remaining' => 60, // Default fallback
        ];
    }
}

function cloneConnectionCharacterId($conn) {
    return isset($conn->characterId) ? $conn->characterId : null;
}
