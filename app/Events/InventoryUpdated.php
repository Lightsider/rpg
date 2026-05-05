<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $characterId
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('character.' . $this->characterId)];
    }

    public function broadcastAs(): string
    {
        return 'inventory.update';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'inventory_update',
            'character_id' => $this->characterId,
        ];
    }
}

