<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Guardian Sword (TANK)
        $gSword = ItemModel::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Guardian Sword',
                'type' => 'weapon',
                'archetype' => 'tank',
                'min_damage' => 12.0000,
                'max_damage' => 14.0000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.1,
                'block_break_rating' => 20,
                'required_strength' => 10,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
            ]
        );

        // 2. Guardian Axe (TANK)
        $gAxe = ItemModel::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Guardian Axe',
                'type' => 'weapon',
                'archetype' => 'tank',
                'min_damage' => 12.0000,
                'max_damage' => 14.0000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 70,
                'required_strength' => 10,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
            ]
        );

        // 3. Executioner Sword (CRIT)
        $eSword = ItemModel::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Executioner Sword',
                'type' => 'weapon',
                'archetype' => 'crit',
                'min_damage' => 6.0000,
                'max_damage' => 8.0000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.1,
                'block_break_rating' => 10,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 7.0000,
            ]
        );

        // 4. Executioner Axe (CRIT)
        $eAxe = ItemModel::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Executioner Axe',
                'type' => 'weapon',
                'archetype' => 'crit',
                'min_damage' => 6.0000,
                'max_damage' => 8.0000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 40,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 7.0000,
            ]
        );

        // 5. Balanced Sword (UNIVERSAL)
        $bSword = ItemModel::updateOrCreate(
            ['id' => 5],
            [
                'name' => 'Balanced Sword',
                'type' => 'weapon',
                'archetype' => 'universal',
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.1,
                'block_break_rating' => 30,
                'required_strength' => 7,
                'required_wit' => 3,
                'flat_crit_bonus' => 3.0000,
            ]
        );

        // 6. Balanced Axe (UNIVERSAL)
        $bAxe = ItemModel::updateOrCreate(
            ['id' => 6],
            [
                'name' => 'Balanced Axe',
                'type' => 'weapon',
                'archetype' => 'universal',
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 7,
                'required_wit' => 3,
                'flat_crit_bonus' => 3.0000,
            ]
        );

        // --- Combat Seals (30% power) ---

        // 7. Guardian Seal (TANK) - 30% of 12-14 DMG = 3.6-4.2 DMG. Each: 0.9-1.05.
        $gSeal = ItemModel::updateOrCreate(
            ['id' => 7],
            [
                'name' => 'Guardian Seal',
                'type' => 'seal',
                'archetype' => 'tank',
                'min_damage' => 0.9000,
                'max_damage' => 1.0500,
                'damage_type' => 'blunt',
                'required_strength' => 10,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
            ]
        );

        // 8. Executioner Seal (CRIT) - 30% of 7 Crit = 2.1 Crit. Each: 0.525. (No Raw Damage)
        $eSeal = ItemModel::updateOrCreate(
            ['id' => 8],
            [
                'name' => 'Executioner Seal',
                'type' => 'seal',
                'archetype' => 'crit',
                'min_damage' => 0.0000,
                'max_damage' => 0.0000,
                'damage_type' => 'blunt',
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 0.5250,
            ]
        );

        // 9. Balanced Seal (UNIVERSAL) - 30% of 9-11 DMG, 3 Crit = 2.7-3.3 DMG, 0.9 Crit. Each: 0.675-0.825 DMG, 0.225 Crit.
        $bSeal = ItemModel::updateOrCreate(
            ['id' => 9],
            [
                'name' => 'Balanced Seal',
                'type' => 'seal',
                'archetype' => 'universal',
                'min_damage' => 0.6750,
                'max_damage' => 0.8250,
                'damage_type' => 'blunt',
                'required_strength' => 7,
                'required_wit' => 3,
                'flat_crit_bonus' => 0.2250,
            ]
        );

        // Re-assign weapons and seals
        User::all()->each(function (User $user) {
            $charModel = \App\Infrastructure\Eloquent\Models\CharacterModel::where('user_id', $user->id)->first();
            if (!$charModel) return;

            if ($user->strength >= 10) {
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 2 : 1,
                    'seal_1_id' => 7,
                    'seal_2_id' => 7,
                    'seal_3_id' => 7,
                    'seal_4_id' => 7,
                ]);
            } elseif ($user->wit >= 5) {
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 4 : 3,
                    'seal_1_id' => 8,
                    'seal_2_id' => 8,
                    'seal_3_id' => 8,
                    'seal_4_id' => 8,
                ]);
            } else {
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 6 : 5,
                    'seal_1_id' => 9,
                    'seal_2_id' => 9,
                    'seal_3_id' => 9,
                    'seal_4_id' => 9,
                ]);
            }
        });
    }
}
