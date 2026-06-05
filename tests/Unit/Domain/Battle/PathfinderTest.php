<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\Map;
use App\Domain\Battle\Pathfinder;
use PHPUnit\Framework\TestCase;

class PathfinderTest extends TestCase
{
    public function test_finds_direct_path_when_unblocked(): void
    {
        $map = new Map(5, 5);
        $isBlocked = fn(int $x, int $y): bool => false;

        $step = Pathfinder::findNextStep($map, 0, 0, 4, 4, $isBlocked);

        $this->assertNotNull($step);
        // Should move diagonally toward the target
        $this->assertEquals(1, $step['x']);
        $this->assertEquals(1, $step['y']);
    }

    public function test_navigates_around_wall_of_obstacles(): void
    {
        // Map 5x5. Start at (0,2), target at (4,2).
        // A wall of blockers at x=2, y=0..4 except y=4 is open.
        //
        //  . . X . .
        //  . . X . .
        //  S . X . T
        //  . . X . .
        //  . . . . .
        //
        $map = new Map(5, 5);
        $blockedCells = [
            '2:0' => true, '2:1' => true, '2:2' => true, '2:3' => true,
        ];
        $isBlocked = fn(int $x, int $y): bool => isset($blockedCells["$x:$y"]);

        $step = Pathfinder::findNextStep($map, 0, 2, 4, 2, $isBlocked);

        $this->assertNotNull($step, 'Should find a path around the wall.');
        // The bot should start moving down-right to get around the wall at y=4
        // First step should be toward (1,3)
        $this->assertEquals(1, $step['x']);
        $this->assertEquals(3, $step['y']);
    }

    public function test_returns_null_when_completely_blocked(): void
    {
        // Start at (0,0) completely walled in
        $map = new Map(3, 3);
        $isBlocked = fn(int $x, int $y): bool => !($x === 0 && $y === 0) && !($x === 2 && $y === 2);

        $step = Pathfinder::findNextStep($map, 0, 0, 2, 2, $isBlocked);

        $this->assertNull($step, 'Should return null when no path exists.');
    }

    public function test_returns_null_when_already_at_destination(): void
    {
        $map = new Map(5, 5);
        $isBlocked = fn(int $x, int $y): bool => false;

        $step = Pathfinder::findNextStep($map, 2, 2, 2, 2, $isBlocked);

        $this->assertNull($step, 'Should return null when already at destination.');
    }

    public function test_adjacent_destination_returns_direct_step(): void
    {
        $map = new Map(5, 5);
        $isBlocked = fn(int $x, int $y): bool => false;

        $step = Pathfinder::findNextStep($map, 1, 1, 2, 2, $isBlocked);

        $this->assertNotNull($step);
        $this->assertEquals(2, $step['x']);
        $this->assertEquals(2, $step['y']);
    }

    public function test_find_next_step_to_any_picks_closest_goal(): void
    {
        $map = new Map(7, 7);
        $isBlocked = fn(int $x, int $y): bool => false;

        $goals = [
            ['x' => 6, 'y' => 6], // far goal
            ['x' => 2, 'y' => 0], // closer goal
        ];

        $result = Pathfinder::findNextStepToAny($map, 0, 0, $goals, $isBlocked);

        $this->assertNotNull($result);
        // Should head toward the closer goal (2,0)
        $this->assertEquals(1, $result['x']);
        $this->assertEquals(0, $result['y']);
        $this->assertEquals(2, $result['goalX']);
        $this->assertEquals(0, $result['goalY']);
    }

    public function test_distance_returns_correct_bfs_distance(): void
    {
        $map = new Map(5, 5);
        $isBlocked = fn(int $x, int $y): bool => false;

        // Diagonal movement: (0,0) to (3,3) = 3 steps
        $dist = Pathfinder::distance($map, 0, 0, 3, 3, $isBlocked);
        $this->assertEquals(3, $dist);
    }

    public function test_distance_around_obstacle(): void
    {
        // Wall at x=2, y=0..3. Opening at y=4.
        $map = new Map(5, 5);
        $blockedCells = [
            '2:0' => true, '2:1' => true, '2:2' => true, '2:3' => true,
        ];
        $isBlocked = fn(int $x, int $y): bool => isset($blockedCells["$x:$y"]);

        // (0,2) to (4,2) — must go around the wall via y=4
        $dist = Pathfinder::distance($map, 0, 2, 4, 2, $isBlocked);

        // Straight would be 4 steps. Going around: down to (1,3), then (2,4), then (3,3), then (4,2) = 4
        $this->assertGreaterThanOrEqual(4, $dist);
        $this->assertLessThan(PHP_INT_MAX, $dist);
    }

    public function test_navigates_around_characters_on_narrow_map(): void
    {
        // 5x3 map. Bot at (0,1), target at (4,1).
        // Blockers at (1,1) and (2,1) — the middle row is blocked.
        //
        //  . . . . .
        //  B X X . T
        //  . . . . .
        //
        $map = new Map(5, 3);
        $blockedCells = ['1:1' => true, '2:1' => true];
        $isBlocked = fn(int $x, int $y): bool => isset($blockedCells["$x:$y"]);

        $step = Pathfinder::findNextStep($map, 0, 1, 4, 1, $isBlocked);

        $this->assertNotNull($step, 'Should find a path around the blockers.');
        // Should go to (1,0) or (1,2) to navigate around the wall
        $movedDiagonally = ($step['x'] === 1 && ($step['y'] === 0 || $step['y'] === 2));
        $this->assertTrue($movedDiagonally, "Expected step to (1,0) or (1,2) but got ({$step['x']},{$step['y']})");
    }
}
