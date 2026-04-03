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
        // 1. Steadfast Sword (STABLE)
        $gSword = ItemModel::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Steadfast Sword',
                'type' => 'weapon',
                'archetype' => 'stable',
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 20,
                'required_strength' => 8,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
                'crit_chance_bonus' => 0.0000,
                'pierce_multiplier' => 0.50,
                'max_damage_rating' => 90,
            ]
        );

        // 2. Steadfast Axe (STABLE)
        $gAxe = ItemModel::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Steadfast Axe',
                'type' => 'weapon',
                'archetype' => 'stable',
                'min_damage' => 9.0000,
                'max_damage' => 11.0000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 8,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
                'crit_chance_bonus' => 0.0000,
                'pierce_multiplier' => 0.65,
                'max_damage_rating' => 0,
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
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 20,
                'required_strength' => 4,
                'required_wit' => 4,
                'flat_crit_bonus' => 10.0000,
                'crit_chance_bonus' => 5.0000,
                'pierce_multiplier' => 0.50,
                'max_damage_rating' => 90,
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
                'block_break_rating' => 60,
                'required_strength' => 4,
                'required_wit' => 4,
                'flat_crit_bonus' => 10.0000,
                'crit_chance_bonus' => 5.0000,
                'pierce_multiplier' => 0.65,
                'max_damage_rating' => 0,
            ]
        );

        // 5. Versatile Sword (HYBRID)
        $bSword = ItemModel::updateOrCreate(
            ['id' => 5],
            [
                'name' => 'Versatile Sword',
                'type' => 'weapon',
                'archetype' => 'hybrid',
                'min_damage' => 8.5000,
                'max_damage' => 10.5000,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 20,
                'required_strength' => 6,
                'required_wit' => 2,
                'flat_crit_bonus' => 4.0000,
                'crit_chance_bonus' => 3.0000,
                'pierce_multiplier' => 0.50,
                'max_damage_rating' => 90,
            ]
        );

        // 6. Versatile Axe (HYBRID)
        $bAxe = ItemModel::updateOrCreate(
            ['id' => 6],
            [
                'name' => 'Versatile Axe',
                'type' => 'weapon',
                'archetype' => 'hybrid',
                'min_damage' => 8.5000,
                'max_damage' => 10.5000,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'required_strength' => 6,
                'required_wit' => 2,
                'flat_crit_bonus' => 4.0000,
                'crit_chance_bonus' => 2.0000,
                'pierce_multiplier' => 0.65,
                'max_damage_rating' => 0,
            ]
        );

        // --- Combat Seals (30% power) ---

        // 7. Steadfast Seal (STABLE) - 30% of 12-14 DMG = 3.6-4.2 DMG. Each: 0.9-1.05.
        $gSeal = ItemModel::updateOrCreate(
            ['id' => 7],
            [
                'name' => 'Steadfast Seal',
                'type' => 'seal',
                'archetype' => 'stable',
                'min_damage' => 0.6750,
                'max_damage' => 0.8250,
                'damage_type' => 'blunt',
                'required_strength' => 8,
                'required_wit' => 0,
                'flat_crit_bonus' => 0.0000,
                'crit_chance_bonus' => 0.0000,
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
                'required_strength' => 4,
                'required_wit' => 4,
                'flat_crit_bonus' => 0.8000,
                'crit_chance_bonus' => 0.7000,
            ]
        );

        // 9. Versatile Seal (HYBRID) - 30% of 9-11 DMG, 3 Crit = 2.7-3.3 DMG, 0.9 Crit. Each: 0.675-0.825 DMG, 0.225 Crit.
        $bSeal = ItemModel::updateOrCreate(
            ['id' => 9],
            [
                'name' => 'Versatile Seal',
                'type' => 'seal',
                'archetype' => 'hybrid',
                'min_damage' => 0.6,
                'max_damage' => 0.75,
                'damage_type' => 'blunt',
                'required_strength' => 6,
                'required_wit' => 2,
                'flat_crit_bonus' => 0.3,
                'crit_chance_bonus' => 0.5000,
            ]
        );


        // --- Armor Items (12 items) ---
        $this->createArmor();

        // Gear re-assignment removed. Users must now purchase items from the shop.
    }

    private function createArmor(): void
    {
        $archetypes = [
            'Tank' => ['prefix' => 'Guardian', 'archetype' => 'tank', 'ad' => 6.00, 'dodge' => 0.0, 'str' => 0, 'wit' => 0, 'dex' => 0, 'con' => 8, 'start_id' => 10],
            'Dodge' => ['prefix' => 'Shadow', 'archetype' => 'dodge', 'ad' => 0.00, 'dodge' => 3.0, 'str' => 0, 'wit' => 0, 'dex' => 4, 'con' => 4, 'start_id' => 14],
            'Universal' => ['prefix' => 'Balanced', 'archetype' => 'universal', 'ad' => 4.00, 'dodge' => 1.2, 'str' => 0, 'wit' => 0, 'dex' => 2, 'con' => 6, 'start_id' => 18],
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
                        'archetype' => $data['archetype'],
                        'ad_armor' => $data['ad'],
                        'dodge_bonus' => $data['dodge'],
                        'armor_subtype' => $subtype->value,
                        'required_strength' => $data['str'],
                        'required_wit' => $data['wit'],
                        'required_dexterity' => $data['dex'],
                        'required_constitution' => $data['con'],
                    ]
                );
                $offset++;
            }
        }
    }
}
