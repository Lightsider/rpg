<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CharacterStatService;
use Tests\TestCase;

class CharacterStatServiceTest extends TestCase
{
    public function test_calculate_hp_for_con_5(): void
    {
        config(['game.base_hp' => 30, 'game.hp_per_con' => 4]);

        $service = new CharacterStatService();
        $this->assertSame(50, $service->calculateHp(5));
    }

    public function test_calculate_hp_for_con_7(): void
    {
        config(['game.base_hp' => 30, 'game.hp_per_con' => 4]);

        $service = new CharacterStatService();
        $this->assertSame(58, $service->calculateHp(7));
    }

    public function test_calculate_hp_for_con_10(): void
    {
        config(['game.base_hp' => 30, 'game.hp_per_con' => 4]);

        $service = new CharacterStatService();
        $this->assertSame(70, $service->calculateHp(10));
    }
}
