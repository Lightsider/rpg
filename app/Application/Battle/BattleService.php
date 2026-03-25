<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Events\Battle\BattleUpdated;
use App\Events\Battle\BattleEnded;
use App\Events\Battle\RoundStarted;
use App\Events\Battle\BattleJoined;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\Event;

/**
 * Service to handle battle-related logic and state synchronization.
 * Now exclusively uses Laravel Events for real-time updates via Reverb/Echo.
 */
class BattleService
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly QueueAttackAction $queueAttackAction,
        private readonly QueueDefenseAction $queueDefenseAction,
        private readonly QueueMoveAction $queueMoveAction,
        private readonly CommitRoundAction $commitRoundAction
    ) {
        // We no longer need to register listeners inside the constructor for Workerman.
        // Standard Laravel broadcasting handles everything through the Event classes themselves.
    }

    /**
     * Build the full battle state for bootstrapping clients.
     */
    public function buildFullState($battle): array
    {
        $participants = array_values($battle->getParticipants());

        return [
            'id' => $battle->getId(),
            'status' => $battle->getState()->value,
            'round' => $battle->getRoundNumber(),
            'map' => [
                'width' => $battle->getMap()->getWidth(),
                'height' => $battle->getMap()->getHeight(),
            ],
            'players' => array_map(fn(Character $c) => [
                'character_id' => $c->getId(),
                'name' => $c->getName(),
                'hp' => $c->getCurrentHp(),
                'max_hp' => $c->getMaxHp(),
                'x' => $c->getX(),
                'y' => $c->getY(),
            ], $participants),
            'participants' => array_map(fn(Character $c) => [
                'character_id' => $c->getId(),
                'name' => $c->getName(),
                'hp' => $c->getCurrentHp(),
                'max_hp' => $c->getMaxHp(),
            ], $participants),
            'positions' => array_map(fn(Character $c) => [
                'character_id' => $c->getId(),
                'x' => $c->getX(),
                'y' => $c->getY(),
            ], $participants),
            'actions_submitted' => $battle->getCommittedCharacterIds(),
            'timer_remaining' => 60, // Default fallback
        ];
    }
}
