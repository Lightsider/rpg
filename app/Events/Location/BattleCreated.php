<?php

declare(strict_types=1);

namespace App\Events\Location;

use App\Domain\Battle\Battle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $battle
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('location.' . $this->battle['location_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'battle.created';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'battle' => $this->battle,
        ];

        return array_merge(
            [
                'type' => $this->broadcastAs(),
                'payload' => $payload,
            ],
            $payload
        );
    }
}
