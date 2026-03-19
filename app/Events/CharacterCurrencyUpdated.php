<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterCurrencyUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $characterId,
        public readonly int $copper
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('character.' . $this->characterId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'character.currency';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'character_id' => $this->characterId,
            'copper' => $this->copper,
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
