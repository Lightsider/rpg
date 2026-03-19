<?php

declare(strict_types=1);

namespace App\Events\Battle;

use App\Domain\Battle\Battle;
use App\Application\Battle\BattleEventPayloadFactory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the battle channel after each round is resolved.
 *
 * Sends only:
 *   - the round-level combat events (from BattleLogEntry[])
 *   - the updated player states (hp, positions)
 *
 * Clients must NOT recalculate combat — they only render what the server sends.
 */
class BattleUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly array $payload;

    /**
     * @param BattleLogEntry[] $logs
     */
    public function __construct(
        public readonly Battle $battle,
        public readonly array $logs
    ) {
        $this->payload = BattleEventPayloadFactory::updated($battle, $logs);
    }

    /**
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('battle.' . $this->payload['battleId']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'battle.updated';
    }

    public function broadcastWith(): array
    {
        return array_merge(
            [
                'type' => $this->broadcastAs(),
                'payload' => $this->payload,
            ],
            $this->payload
        );
    }
}
