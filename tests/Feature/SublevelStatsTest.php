<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Character\Character;
use PHPUnit\Framework\TestCase;

class SublevelStatsTest extends TestCase
{
    public function test_sublevel_stats_distribution_with_4_sublevels(): void
    {
        $character = new Character(id: 1, userId: 1, name: 'Test', strength: 4, agility: 4, constitution: 4, wit: 4, maxHp: 55, currentHp: 55);

        // Simulated thresholds for level 1 (4 sublevels)
        $thresholds = [
            1 => ['xp_threshold' => 10, 'reward_copper' => 0],
            2 => ['xp_threshold' => 20, 'reward_copper' => 0],
            3 => ['xp_threshold' => 30, 'reward_copper' => 0],
            4 => ['xp_threshold' => 40, 'reward_copper' => 0],
        ];

        // Start at Sublevel 0.
        // Gain 10 XP -> Sublevel 1
        $leveledUp = $character->addExperience(10, $thresholds);
        $this->assertFalse($leveledUp);
        $this->assertEquals(1, $character->getSublevelIndex());
        $this->assertEquals(1, $character->getUnallocatedStats()); // floor(4/4) = 1

        // Gain 10 XP -> Sublevel 2
        $leveledUp = $character->addExperience(10, $thresholds);
        $this->assertFalse($leveledUp);
        $this->assertEquals(2, $character->getSublevelIndex());
        $this->assertEquals(2, $character->getUnallocatedStats());

        // Gain 10 XP -> Sublevel 3
        $leveledUp = $character->addExperience(10, $thresholds);
        $this->assertFalse($leveledUp);
        $this->assertEquals(3, $character->getSublevelIndex());
        $this->assertEquals(3, $character->getUnallocatedStats());

        // Gain 10 XP -> Sublevel 4 (Level up to Level 2)
        $leveledUp = $character->addExperience(10, $thresholds);
        $this->assertTrue($leveledUp);
        $this->assertEquals(0, $character->getSublevelIndex());
        $this->assertEquals(2, $character->getLevel());
        
        // Remaining stats = 8 - (1 * 4) = 4. Total should be 3 + 1 + 4 = 8
        $this->assertEquals(8, $character->getUnallocatedStats());
    }

    public function test_sublevel_stats_distribution_with_3_sublevels(): void
    {
        $character = new Character(id: 1, userId: 1, name: 'Test', strength: 4, agility: 4, constitution: 4, wit: 4, maxHp: 55, currentHp: 55);

        // Simulated thresholds for level 1 (3 sublevels)
        $thresholds = [
            1 => ['xp_threshold' => 10, 'reward_copper' => 0],
            2 => ['xp_threshold' => 20, 'reward_copper' => 0],
            3 => ['xp_threshold' => 30, 'reward_copper' => 0],
        ];

        // Stats per sublevel = floor(4/3) = 1
        $character->addExperience(10, $thresholds);
        $this->assertEquals(1, $character->getUnallocatedStats());

        $character->addExperience(10, $thresholds);
        $this->assertEquals(2, $character->getUnallocatedStats());

        $character->addExperience(10, $thresholds);
        $this->assertEquals(2, $character->getLevel());
        // Sublevel 3 triggers level up. Total from sublevels: 3.
        // Remaining: 8 - (1 * 3) = 5.
        // Final total: 2 (from prev) + 1 (from sl 3) + 5 (remaining) = 8
        $this->assertEquals(8, $character->getUnallocatedStats());
    }

    public function test_sublevel_stats_distribution_with_2_sublevels(): void
    {
        $character = new Character(id: 1, userId: 1, name: 'Test', strength: 4, agility: 4, constitution: 4, wit: 4, maxHp: 55, currentHp: 55);

        $thresholds = [
            1 => ['xp_threshold' => 10, 'reward_copper' => 0],
            2 => ['xp_threshold' => 20, 'reward_copper' => 0],
        ];

        // Stats per sublevel = floor(4/2) = 2
        $character->addExperience(10, $thresholds);
        $this->assertEquals(2, $character->getUnallocatedStats());

        $character->addExperience(10, $thresholds);
        $this->assertEquals(2, $character->getLevel());
        // Remaining: 8 - (2 * 2) = 4.
        // Final total: 2 (from prev) + 2 (from sl 2) + 4 (remaining) = 8
        $this->assertEquals(8, $character->getUnallocatedStats());
    }

    public function test_multi_level_jump_stats(): void
    {
        $character = new Character(id: 1, userId: 1, name: 'Test', strength: 4, agility: 4, constitution: 4, wit: 4, maxHp: 55, currentHp: 55);

        $thresholds = [
            1 => ['xp_threshold' => 10, 'reward_copper' => 0],
            2 => ['xp_threshold' => 20, 'reward_copper' => 0],
        ];

        // Gain 100 XP all at once
        $character->addExperience(100, $thresholds);
        $this->assertEquals(2, $character->getLevel());
        $this->assertEquals(8, $character->getUnallocatedStats());
    }
}
