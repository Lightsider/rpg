<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogEntry;
use App\Domain\Character\Character;
use App\Infrastructure\Eloquent\Models\FightMapModel;

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
                'outcome' => $entry->outcome,
                'is_crit' => $entry->isCrit,
                'is_max' => $entry->isMax,
                'weapon_name' => $entry->weaponName,
                'damage_type' => $entry->damageType,
                'occurred_at' => $entry->timestamp->format(\DateTimeInterface::ATOM),
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
                    'team' => $battle->getParticipantTeam($c->getId()),
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
                'team' => $battle->getParticipantTeam($c->getId()),
            ],
            $participants
        );

        $mapModel = FightMapModel::where('fight_id', $battle->getId())->first();
        $mapWidth = $mapModel?->width ?? $battle->getMap()->getWidth();
        $mapHeight = $mapModel?->height ?? $battle->getMap()->getHeight();

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
                'max_hp' => $p['max_hp'],
                'team' => $p['team'],
            ], $participantsData),
            'map' => [
                'width' => $mapWidth,
                'height' => $mapHeight,
            ],
            'positions' => array_map(
                fn($p) => [
                    'character_id' => $p['character_id'],
                    'x' => $p['x'],
                    'y' => $p['y'],
                    'team' => $p['team'] ?? null,
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
        $winners = $battle->getVictoryWinners();
        $winner = $winners[0] ?? null;

        return [
            'battle_id' => $battle->getId(),
            'winner_id' => $winner?->getId(),
            'winner_ids' => array_map(fn(Character $c) => $c->getId(), $winners),
            'winner_team' => $battle->getWinningTeamName(),
            'reason' => $winner === null ? 'draw' : 'knockout',
        ];
    }
}
