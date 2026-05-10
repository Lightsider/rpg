<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleViewReadRepositoryInterface;
use App\Domain\Character\Character;

class BattleViewFactory
{
    public function __construct(
        private readonly BattleViewReadRepositoryInterface $battleViewReadRepository
    ) {
    }

    public function build(Battle $battle): array
    {
        $snapshot = $this->battleViewReadRepository->getMapSnapshot(
            $battle->getId(),
            $battle->getMap()->getWidth(),
            $battle->getMap()->getHeight()
        );

        return [
            'battle_id' => $battle->getId(),
            'status' => $battle->getState()->value,
            'round' => $battle->getRoundNumber(),
            'participants' => array_map(fn(\App\Domain\Battle\Combatant $p) => [
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
                'width' => $snapshot['width'],
                'height' => $snapshot['height'],
            ],
            'positions' => array_map(function ($pos) use ($battle) {
                $participant = $battle->getParticipantById($pos['character_id']);
                $pos['team'] = $battle->getParticipantTeam($pos['character_id']);
                $pos['hp'] = $participant ? $participant->getCurrentHp() : 0;
                return $pos;
            }, $snapshot['positions']),
            'actions_submitted' => $battle->getCommittedCharacterIds(),
            'rewards' => $battle->getRewards(),
            'winning_team' => $battle->getWinningTeamName(),
        ];
    }
}
