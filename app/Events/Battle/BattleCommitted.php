<?php

declare(strict_types=1);

namespace App\Events\Battle;

use App\Domain\Battle\Battle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the battle channel when a participant commits their actions.
 */
class BattleCommitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Battle $battle,
        public readonly int $characterId
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('battle.' . $this->battle->getId()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'battle.committed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->battle->getId(),
            'character_id' => $this->characterId,
            'committed_character_ids' => $this->battle->getCommittedCharacterIds(),
        ];
    }
}
