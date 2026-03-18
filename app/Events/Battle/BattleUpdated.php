<?php

declare(strict_types=1);

namespace App\Events\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Character\Character;
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
        $events = array_map(
            fn(BattleLogEntry $entry) => array_filter([
                'type'     => $entry->type->value,
                'actorId'  => $entry->actorId,
                'targetId' => $entry->targetId,
                'zone'     => $entry->zone?->value,
                'damage'   => $entry->damage,
            ], fn($v) => $v !== null),
            $logs
        );

        $this->payload = [
            'id'       => $battle->getId(),
            'battleId' => $battle->getId(),
            'round'    => $battle->getRoundNumber(),
            'status'   => $battle->getState()->value,
            'events'   => array_values($events),
            'players'  => array_map(
                fn(Character $c) => [
                    'character_id' => $c->getId(),
                    'hp'           => $c->getCurrentHp(),
                    'max_hp'       => $c->getMaxHp(),
                    'x'            => $c->getX(),
                    'y'            => $c->getY(),
                ],
                array_values($battle->getParticipants())
            ),
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
        return 'battle.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
