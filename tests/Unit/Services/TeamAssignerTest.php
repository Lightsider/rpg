<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TeamAssigner;
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

    public function test_assign_random_when_balanced(): void
    {
        $assigner = new TeamAssigner();

        $team = $assigner->assign([
            1 => 'blue',
            2 => 'red',
        ]);

        $this->assertContains($team, ['blue', 'red']);
    }
}
