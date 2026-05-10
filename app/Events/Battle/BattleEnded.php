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
 * Broadcast to the battle channel when the battle finishes.
 */
class BattleEnded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly array $payload;

    public function __construct(
        public readonly Battle $battle
    ) {
        $this->payload = BattleEventPayloadFactory::ended($battle);
    }

    /**
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('battle.' . $this->payload['battle_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'battle.ended';
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
