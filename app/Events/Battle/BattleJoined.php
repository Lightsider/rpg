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
 * Broadcast to a battle channel when a player joins.
 * Contains the full battle state so the joining client has everything it needs.
 */
class BattleJoined implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly array $payload;

    public function __construct(
        public readonly Battle $battle,
        public readonly int $timerRemaining
    ) {
        $participants = array_values($battle->getParticipants());

        $this->payload = [
            'id'             => $battle->getId(),
            'round'          => $battle->getRoundNumber(),
            'status'         => $battle->getState()->value,
            'timer_remaining' => $timerRemaining,
            'players'        => array_map(
                fn(Character $c) => [
                    'character_id' => $c->getId(),
                    'name'         => $c->getName(),
                    'hp'           => $c->getCurrentHp(),
                    'max_hp'       => $c->getMaxHp(),
                    'x'            => $c->getX(),
                    'y'            => $c->getY(),
                ],
                $participants
            ),
            'map' => [
                'width'  => $battle->getMap()->getWidth(),
                'height' => $battle->getMap()->getHeight(),
            ],
            'positions' => array_map(
                fn(Character $c) => [
                    'character_id' => $c->getId(),
                    'x'            => $c->getX(),
                    'y'            => $c->getY(),
                ],
                $participants
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
        return 'battle.joined';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
