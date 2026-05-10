<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Character\Character;

class BattleSummaryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Battle $battle): array
    {
        return [
            'id' => $battle->getId(),
            'location_id' => $battle->getLocationId(),
            'participants' => array_map(
                fn(\App\Domain\Battle\Combatant $p) => $p->getName(),
                array_values($battle->getParticipants())
            ),
            'participant_ids' => array_keys($battle->getParticipants()),
            'round_number' => $battle->getRoundNumber(),
            'state' => $battle->getState()->value,
            'committed_character_ids' => $battle->getCommittedCharacterIds(),
            'max_participants' => $battle->getMaxParticipants(),
            'start_timeout_seconds' => $battle->getStartTimeoutSeconds(),
            'participant_teams' => $battle->getParticipantTeams(),
            'winner_ids' => $battle->getWinnerIds(),
            'rewards' => $battle->getRewards(),
            'round_started_at' => $battle->getRoundStartedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

