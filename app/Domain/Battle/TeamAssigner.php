<?php

declare(strict_types=1);

namespace App\Domain\Battle;

class TeamAssigner
{
    private const string TEAM_BLUE = 'blue';
    private const string TEAM_RED = 'red';

    /**
     * @param array<int, string> $currentTeams
     */
    public function assign(array $currentTeams): string
    {
        $counts = [
            self::TEAM_BLUE => 0,
            self::TEAM_RED => 0,
        ];

        foreach ($currentTeams as $team) {
            if (isset($counts[$team])) {
                $counts[$team]++;
            }
        }

        if ($counts[self::TEAM_BLUE] === $counts[self::TEAM_RED]) {
            return self::TEAM_BLUE;
        }

        return $counts[self::TEAM_BLUE] < $counts[self::TEAM_RED]
            ? self::TEAM_BLUE
            : self::TEAM_RED;
    }
}
