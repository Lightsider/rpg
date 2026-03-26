<?php

namespace App\Infrastructure\WebSockets\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdateEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $characterId;

    public function __construct(int $characterId)
    {
        $this->characterId = $characterId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('character.' . $this->characterId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inventory.update';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'inventory_update',
            // Ideally we'd send the full backpack or just a ping to refresh loadout
            'character_id' => $this->characterId,
        ];
    }
}
