<?php

namespace Database\Factories;

use App\Infrastructure\Eloquent\Models\LocationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationModelFactory extends Factory
{
    protected $model = LocationModel::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Location',
            'description' => 'A test location description.',
            'max_players' => 10,
            'start_timeout_seconds' => 60,
        ];
    }
}
