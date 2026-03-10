<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sword = \App\Infrastructure\Eloquent\Models\ItemModel::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Sword',
                'type' => 'weapon',
                'min_damage' => 8,
                'max_damage' => 14,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 20,
                'pierce_multiplier' => 0.50,
                'max_damage_rating' => 90,
            ]
        );

        $axe = \App\Infrastructure\Eloquent\Models\ItemModel::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Axe',
                'type' => 'weapon',
                'min_damage' => 8,
                'max_damage' => 14,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'pierce_multiplier' => 0.65,
                'max_damage_rating' => 0,
            ]
        );

        // Update existing users to use these items
        \App\Infrastructure\Eloquent\Models\User::where('weapon', 'sword')->update(['weapon_id' => $sword->id]);
        \App\Infrastructure\Eloquent\Models\User::where('weapon', 'axe')->update(['weapon_id' => $axe->id]);
    }
}
