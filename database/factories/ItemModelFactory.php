<?php

namespace Database\Factories;

use App\Infrastructure\Eloquent\Models\ItemModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemModelFactory extends Factory
{
    protected $model = ItemModel::class;

    public function definition(): array
    {
        return [
            'name' => 'Rusty Sword',
            'type' => 'weapon',
            'min_damage' => 1.0,
            'max_damage' => 2.0,
            'damage_type' => 'slashing',
            'required_strength' => 0,
            'required_wit' => 0,
            'is_two_handed' => false,
        ];
    }
}
