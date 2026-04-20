<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Character\Character;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;

class BattleViewFactory
{
    public function build(Battle $battle): array
    {
        $mapModel = FightMapModel::where('fight_id', $battle->getId())->first();
        $mapWidth = $mapModel?->width ?? $battle->getMap()->getWidth();
        $mapHeight = $mapModel?->height ?? $battle->getMap()->getHeight();

        $positions = FighterPositionModel::where('fight_id', $battle->getId())
            ->get()
            ->map(fn(FighterPositionModel $pos) => [
                'character_id' => $pos->character_id,
                'x' => $pos->x,
                'y' => $pos->y,
            ])->toArray();

        return [
            'battle_id' => $battle->getId(),
            'status' => $battle->getState()->value,
            'round' => $battle->getRoundNumber(),
            'participants' => array_map(fn(Character $p) => [
                'character_id' => $p->getId(),
                'name' => $p->getName(),
                'hp' => $p->getCurrentHp(),
                'max_hp' => $p->getMaxHp(),
                'team' => $battle->getParticipantTeam($p->getId()),
                'additional_armor' => [
                    'head' => $p->getAdArmorForZone('head'),
                    'chest' => $p->getAdArmorForZone('chest'),
                    'legs' => $p->getAdArmorForZone('legs'),
                    'left_arm' => $p->getAdArmorForZone('left_arm'),
                    'right_arm' => $p->getAdArmorForZone('right_arm'),
                ],
            ], array_values($battle->getParticipants())),
            'map' => [
                'width' => $mapWidth,
                'height' => $mapHeight,
            ],
            'positions' => $positions,
            'actions_submitted' => $battle->getCommittedCharacterIds(),
            'rewards' => $battle->getRewards(),
        ];
    }
}
