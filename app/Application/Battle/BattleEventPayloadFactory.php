<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Character\Character;

class BattleEventPayloadFactory
{
    /**
     * @param BattleLogEntry[] $logs
     */
    public static function updated(Battle $battle, array $logs): array
    {
        $events = array_map(
            fn(BattleLogEntry $entry) => array_filter([
                'type' => $entry->type->value,
                'actor_id' => $entry->actorId,
                'target_id' => $entry->targetId,
                'zone' => $entry->zone?->value,
                'damage' => $entry->damage,
            ], fn($v) => $v !== null),
            $logs
        );

        return [
            'battle_id' => $battle->getId(),
            'round' => $battle->getRoundNumber(),
            'status' => $battle->getState()->value,
            'events' => array_values($events),
            'players' => array_map(
                fn(Character $c) => [
                    'character_id' => $c->getId(),
                    'hp' => $c->getCurrentHp(),
                    'max_hp' => $c->getMaxHp(),
                    'x' => $c->getX(),
                    'y' => $c->getY(),
                ],
                array_values($battle->getParticipants())
            ),
        ];
    }

    public static function joined(Battle $battle, int $timerRemaining): array
    {
        $participants = array_values($battle->getParticipants());

        $participantsData = array_map(
            fn(Character $c) => [
                'character_id' => $c->getId(),
                'name' => $c->getName(),
                'hp' => $c->getCurrentHp(),
                'max_hp' => $c->getMaxHp(),
                'x' => $c->getX(),
                'y' => $c->getY(),
            ],
            $participants
        );

        return [
            'battle_id' => $battle->getId(),
            'round' => $battle->getRoundNumber(),
            'status' => $battle->getState()->value,
            'timer_remaining' => $timerRemaining,
            'players' => $participantsData,
            'participants' => array_map(fn($p) => [
                'character_id' => $p['character_id'],
                'name' => $p['name'],
                'hp' => $p['hp'],
                'max_hp' => $p['max_hp']
            ], $participantsData),
            'map' => [
                'width' => $battle->getMap()->getWidth(),
                'height' => $battle->getMap()->getHeight(),
            ],
            'positions' => array_map(
                fn($p) => [
                    'character_id' => $p['character_id'],
                    'x' => $p['x'],
                    'y' => $p['y'],
                ],
                $participantsData
            ),
            'actions_submitted' => $battle->getCommittedCharacterIds(),
        ];
    }

    public static function committed(Battle $battle, int $characterId): array
    {
        return [
            'battle_id' => $battle->getId(),
            'character_id' => $characterId,
            'committed_character_ids' => $battle->getCommittedCharacterIds(),
        ];
    }

    public static function ended(Battle $battle): array
    {
        $winner = null;
        foreach ($battle->getParticipants() as $participant) {
            if ($participant->getCurrentHp() > 0) {
                $winner = $participant;
                break;
            }
        }

        return [
            'battle_id' => $battle->getId(),
            'winner_id' => $winner?->getId(),
            'reason' => $winner === null ? 'draw' : 'knockout',
        ];
    }
}
