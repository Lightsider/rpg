<?php

namespace App\Infrastructure\WebSockets\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreStateEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $characterId;
    public array $items;

    public function __construct(int $characterId, array $items)
    {
        $this->characterId = $characterId;
        $this->items = $items;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('character.' . $this->characterId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'store.state';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'store_state',
            'items' => $this->items,
        ];
    }
}
