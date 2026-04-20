<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Character\Character;

/**
 * Service to build battle state payloads for clients.
 */
class BattleService
{
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
                'team' => $battle->getParticipantTeam($c->getId()),
            ], $participants),
            'participants' => array_map(fn(Character $c) => [
                'character_id' => $c->getId(),
                'name' => $c->getName(),
                'hp' => $c->getCurrentHp(),
                'max_hp' => $c->getMaxHp(),
                'team' => $battle->getParticipantTeam($c->getId()),
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
