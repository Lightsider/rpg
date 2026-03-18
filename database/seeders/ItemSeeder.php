<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Guardian Sword (TANK)
        $gSword = ItemModel::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Guardian Sword',
                'type' => 'weapon',
                'archetype' => 'tank',
                'min_damage' => 12,
                'max_damage' => 14,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.10,
                'block_break_rating' => 20,
                'required_strength' => 10,
                'required_wit' => 0,
                'flat_crit_bonus' => 0,
            ]
        );

        // 2. Guardian Axe (TANK)
        $gAxe = ItemModel::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Guardian Axe',
                'type' => 'weapon',
                'archetype' => 'tank',
                'min_damage' => 12,
                'max_damage' => 14,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 10,
                'required_wit' => 0,
                'flat_crit_bonus' => 0,
            ]
        );

        // 3. Executioner Sword (CRIT)
        $eSword = ItemModel::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Executioner Sword',
                'type' => 'weapon',
                'archetype' => 'crit',
                'min_damage' => 6,
                'max_damage' => 8,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.10,
                'block_break_rating' => 20,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 7,
            ]
        );

        // 4. Executioner Axe (CRIT)
        $eAxe = ItemModel::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Executioner Axe',
                'type' => 'weapon',
                'archetype' => 'crit',
                'min_damage' => 6,
                'max_damage' => 8,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 7,
            ]
        );

        // 5. Balanced Sword (UNIVERSAL)
        $bSword = ItemModel::updateOrCreate(
            ['id' => 5],
            [
                'name' => 'Balanced Sword',
                'type' => 'weapon',
                'archetype' => 'universal',
                'min_damage' => 9,
                'max_damage' => 11,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.10,
                'block_break_rating' => 20,
                'required_strength' => 7,
                'required_wit' => 3,
                'flat_crit_bonus' => 3,
            ]
        );

        // 6. Balanced Axe (UNIVERSAL)
        $bAxe = ItemModel::updateOrCreate(
            ['id' => 6],
            [
                'name' => 'Balanced Axe',
                'type' => 'weapon',
                'archetype' => 'universal',
                'min_damage' => 9,
                'max_damage' => 11,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 7,
                'required_wit' => 3,
                'flat_crit_bonus' => 3,
            ]
        );

        // Re-assign weapons to existing users for testing
        // Users with high STR get Guardian, high WIT get Executioner, others Balanced.
        User::all()->each(function (User $user) {
            if ($user->strength >= 10) {
                $user->update(['weapon_id' => ($user->id % 2 === 0) ? 2 : 1]); // Guardian
            } elseif ($user->wit >= 5) {
                $user->update(['weapon_id' => ($user->id % 2 === 0) ? 4 : 3]); // Executioner
            } else {
                $user->update(['weapon_id' => ($user->id % 2 === 0) ? 6 : 5]); // Balanced
            }
        });
    }
}
