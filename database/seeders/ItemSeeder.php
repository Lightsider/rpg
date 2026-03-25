<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Item\ItemType;

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
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
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
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
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
                'min_damage' => 7.0000,
                'max_damage' => 9.0000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.1,
                'block_break_rating' => 10,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 10.0000,
            ]
        );

        // 4. Executioner Axe (CRIT)
        $eAxe = ItemModel::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Executioner Axe',
                'type' => 'weapon',
                'archetype' => 'crit',
                'min_damage' => 7.0000,
                'max_damage' => 9.0000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 40,
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 10.0000,
            ]
        );

        // 5. Balanced Sword (UNIVERSAL)
        $bSword = ItemModel::updateOrCreate(
            ['id' => 5],
            [
                'name' => 'Balanced Sword',
                'type' => 'weapon',
                'archetype' => 'universal',
                'min_damage' => 8.5000,
                'max_damage' => 10.5000,
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
                'min_damage' => 8.5000,
                'max_damage' => 10.5000,
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
                'min_damage' => 0.6000,
                'max_damage' => 0.7500,
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
                'min_damage' => 0.5000,
                'max_damage' => 0.6500,
                'damage_type' => 'blunt',
                'required_strength' => 5,
                'required_wit' => 5,
                'flat_crit_bonus' => 0.8000,
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


        // --- Armor Items (12 items) ---
        $this->createArmor();

        // Re-assign gear based on archetypes
        User::all()->each(function (User $user) {
            $charModel = \App\Infrastructure\Eloquent\Models\CharacterModel::where('user_id', $user->id)->first();
            if (!$charModel) return;

            if ($user->strength >= 10) {
                // TANK
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 2 : 1, // Axe or Sword
                    'seal_1_id' => 7, 'seal_2_id' => 7, 'seal_3_id' => 7, 'seal_4_id' => 7,
                    'helmet_id' => 10, 'chest_id' => 11, 'legs_id' => 12, 'gloves_id' => 13,
                    'ad_armor_head' => 12, 'ad_armor_chest' => 12, 'ad_armor_legs' => 12, 'ad_armor_hands' => 12,
                ]);
            } elseif ($user->wit >= 5) {
                // CRIT / DODGE (Dodge items for high WIT)
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 4 : 3, // Dagger or Rapier
                    'seal_1_id' => 8, 'seal_2_id' => 8, 'seal_3_id' => 8, 'seal_4_id' => 8,
                    'helmet_id' => 14, 'chest_id' => 15, 'legs_id' => 16, 'gloves_id' => 17,
                    'ad_armor_head' => 0, 'ad_armor_chest' => 0, 'ad_armor_legs' => 0, 'ad_armor_hands' => 0,
                ]);
            } else {
                // UNIVERSAL
                $charModel->update([
                    'weapon_id' => ($user->id % 2 === 0) ? 6 : 5, // Balanced Axe or Sword
                    'seal_1_id' => 9, 'seal_2_id' => 9, 'seal_3_id' => 9, 'seal_4_id' => 9,
                    'helmet_id' => 18, 'chest_id' => 19, 'legs_id' => 20, 'gloves_id' => 21,
                    'ad_armor_head' => 8.4, 'ad_armor_chest' => 8.4, 'ad_armor_legs' => 8.4, 'ad_armor_hands' => 8.4,
                ]);
            }
        });
    }

    private function createArmor(): void
    {
        $archetypes = [
            'Tank' => ['prefix' => 'Guardian', 'ad' => 10.00, 'dodge' => 0.0, 'str' => 10, 'wit' => 0, 'start_id' => 10],
            'Dodge' => ['prefix' => 'Shadow', 'ad' => 0.00, 'dodge' => 5.0, 'str' => 0, 'wit' => 10, 'start_id' => 14],
            'Universal' => ['prefix' => 'Balanced', 'ad' => 7.00, 'dodge' => 1.5, 'str' => 7, 'wit' => 3, 'start_id' => 18],
        ];

        $subtypes = [
            'Helmet' => ArmorSubtype::HELMET,
            'Chestplate' => ArmorSubtype::BODY,
            'Boots' => ArmorSubtype::BOOTS,
            'Gauntlets' => ArmorSubtype::GLOVES,
        ];

        foreach ($archetypes as $arch => $data) {
            $offset = 0;
            foreach ($subtypes as $suffix => $subtype) {
                ItemModel::updateOrCreate(
                    ['id' => $data['start_id'] + $offset],
                    [
                        'name' => "{$data['prefix']} {$suffix}",
                        'type' => ItemType::ARMOR->value,
                        'ad_armor' => $data['ad'],
                        'dodge_bonus' => $data['dodge'],
                        'armor_subtype' => $subtype->value,
                        'required_strength' => $data['str'],
                        'required_wit' => $data['wit'],
                    ]
                );
                $offset++;
            }
        }
    }
}
