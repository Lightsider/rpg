<?php

declare(strict_types=1);

namespace App\Events\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Character\Character;
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
        $winner = $this->findWinner($battle);

        $this->payload = [
            'battleId' => $battle->getId(),
            'winnerId' => $winner?->getId(),
            'reason'   => $winner === null ? 'draw' : 'knockout',
        ];
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
        return 'battle.ended';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    private function findWinner(Battle $battle): ?Character
    {
        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getCurrentHp() > 0) {
                return $participant;
            }
        }

        return null; // draw — both dead simultaneously
    }
}
