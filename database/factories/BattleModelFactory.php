<?php

namespace Database\Factories;

use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class BattleModelFactory extends Factory
{
    protected $model = BattleModel::class;

    public function definition(): array
    {
        return [
            'state' => 'active',
            'location_id' => LocationModel::factory(),
            'round_number' => 1,
            'round_started_at' => now(),
            'round_duration_seconds' => 30,
            'max_participants' => 2,
            'committed_character_ids' => [],
            'map_width' => 10,
            'map_height' => 10,
        ];
    }
}
