<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;

class CharacterLevelingTest extends TestCase
{
    public function test_character_levels_up_when_xp_reaches_threshold(): void
    {
        $xpRequirements = [
            2 => 1200,
            3 => 3000,
        ];

        $character = new Character(
            id: 1,
            userId: 1,
            name: 'Test',
            strength: 4,
            agility: 4,
            constitution: 4,
            wit: 4,
            maxHp: 100,
            currentHp: 100,
            equipment: new Equipment(),
            level: 1,
            experience: 0
        );

        $this->assertEquals(1, $character->getLevel());
        $this->assertEquals(0, $character->getExperience());

        // Add 1199 XP (should not level up)
        $leveledUp = $character->addExperience(1199, $xpRequirements);
        $this->assertFalse($leveledUp);
        $this->assertEquals(1, $character->getLevel());
        $this->assertEquals(1199, $character->getExperience());

        // Add 1 XP (should level up)
        $leveledUp = $character->addExperience(1, $xpRequirements);
        $this->assertTrue($leveledUp);
        $this->assertEquals(2, $character->getLevel());
        $this->assertEquals(1200, $character->getExperience());

        // Add enough XP for Level 3
        $leveledUp = $character->addExperience(1800, $xpRequirements); // Total 3000
        $this->assertTrue($leveledUp);
        $this->assertEquals(3, $character->getLevel());
        $this->assertEquals(3000, $character->getExperience());
    }

    public function test_character_can_level_up_multiple_times(): void
    {
        $xpRequirements = [
            2 => 100,
            3 => 300,
            4 => 600,
        ];

        $character = new Character(
            id: 1,
            userId: 1,
            name: 'Test',
            strength: 4,
            agility: 4,
            constitution: 4,
            wit: 4,
            maxHp: 100,
            currentHp: 100,
            equipment: new Equipment(),
            level: 1,
            experience: 0
        );

        // Add 700 XP at once
        $leveledUp = $character->addExperience(700, $xpRequirements);
        $this->assertTrue($leveledUp);
        $this->assertEquals(4, $character->getLevel());
        $this->assertEquals(700, $character->getExperience());
    }
}
