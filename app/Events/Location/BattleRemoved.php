<?php

declare(strict_types=1);

namespace App\Events\Location;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcasted to the location channel when a battle is removed from the lobby.
 * This happens when a battle starts (becomes ACTIVE) or is cancelled.
 */
class BattleRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $locationId,
        public readonly int $battleId
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('location.' . $this->locationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'battle.removed';
    }
}
