<?php

namespace Database\Factories;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\LocationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class CharacterModelFactory extends Factory
{
    protected $model = CharacterModel::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'level' => 1,
            'experience' => 0,
            'strength' => 4,
            'dexterity' => 4,
            'constitution' => 4,
            'wit' => 4,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => LocationModel::factory(),
            'x' => 0,
            'y' => 0,
            'currency_copper' => 0,
        ];
    }
}
