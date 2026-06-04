<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\TeamAssigner;
use PHPUnit\Framework\TestCase;

class TeamAssignerTest extends TestCase
{
    public function test_assign_prefers_smaller_team(): void
    {
        $assigner = new TeamAssigner();

        $team = $assigner->assign([
            1 => 'blue',
            2 => 'blue',
            3 => 'red',
        ]);

        $this->assertSame('red', $team);
    }

    public function test_assign_uses_deterministic_blue_tie_break_when_balanced(): void
    {
        $assigner = new TeamAssigner();

        $team = $assigner->assign([
            1 => 'blue',
            2 => 'red',
        ]);

        $this->assertSame('blue', $team);
    }
}
