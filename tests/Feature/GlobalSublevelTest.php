<?php

namespace Tests\Feature;

use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Infrastructure\Eloquent\Models\LevelSublevelModel;
use App\Infrastructure\Eloquent\Repositories\LevelSublevelRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSublevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_sublevels_command(): void
    {
        // Run command
        $this->artisan('app:generate-sublevels --levels=5')->assertExitCode(0);

        // Check level 1 (1+2=3 sublevels)
        // Level 2 XP in config is 1200.
        // Weights: 1, 2, 3 (sum 6)
        // XP Unit: 200.
        // SL1: 200, SL2: 600, SL3: 1200.
        $this->assertDatabaseHas('level_sublevels', [
            'level' => 1,
            'sublevel_index' => 1,
            'xp_threshold' => 200
        ]);
        $this->assertDatabaseHas('level_sublevels', [
            'level' => 1,
            'sublevel_index' => 3,
            'xp_threshold' => 1200
        ]);
    }

    public function test_character_uses_global_thresholds(): void
    {
        // 1. Setup stable thresholds
        LevelSublevelModel::create([
            'level' => 1,
            'sublevel_index' => 1,
            'xp_threshold' => 500,
            'reward_copper' => 100
        ]);
        LevelSublevelModel::create([
            'level' => 1,
            'sublevel_index' => 2,
            'xp_threshold' => 1200,
            'reward_copper' => 200
        ]);

        $char = new Character(
            id: 1,
            userId: 1,
            name: "Tester",
            strength: 5,
            agility: 5,
            constitution: 5,
            wit: 5,
            maxHp: 100,
            currentHp: 100,
            equipment: new Equipment(),
            level: 1,
            experience: 0,
            sublevelIndex: 0,
            currencyCopper: 0
        );

        $repo = new LevelSublevelRepository();
        $thresholds = $repo->getThresholdsForLevel(1);

        // 2. Add XP. Should reach SL1 at 500 XP.
        $char->addExperience(600, $thresholds);
        
        $this->assertEquals(1, $char->getSublevelIndex());
        $this->assertEquals(100, $char->getCurrencyCopper());

        // 3. Add more XP to level up.
        $char->addExperience(600, $thresholds);
        $this->assertEquals(2, $char->getLevel());
        $this->assertEquals(0, $char->getSublevelIndex());
        $this->assertEquals(300, $char->getCurrencyCopper());
    }
}
