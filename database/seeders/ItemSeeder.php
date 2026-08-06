<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Item\ItemType;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // Level 1 Items
        $this->createStableItems();
        $this->createCritItems();
        $this->createHybridItems();
        $this->createSeals();
        $this->createShields();
        $this->createArmor();

        // Level 2 Items
        $this->createStableItemsLvl2();
        $this->createCritItemsLvl2();
        $this->createHybridItemsLvl2();
        $this->createSealsLvl2();
        $this->createShieldsLvl2();
        $this->createArmorLvl2();
    }

    private function createStableItems(): void
    {
        $this->createWeapon(1, $this->swordArgs(1), 'Steadfast Sword', 'stable', 9, 11, 0, 0, 8, 0, 1);
        $this->createWeapon(2, $this->axeArgs(1), 'Steadfast Axe', 'stable', 9, 11, 0, 0, 8, 0, 1);
        $this->createWeapon(22, $this->twoHandedArgs(1), 'Steadfast Battleaxe', 'stable', 11.7, 14.3, 0, 0, 8, 0, 1);
        $this->createDagger(25, 'Steadfast Dagger', 'stable', 4.5, 5.5, 0, 0, 8, 0, 5, 20, 1);
    }

    private function createCritItems(): void
    {
        $this->createWeapon(3, $this->swordArgs(1), 'Executioner Sword', 'crit', 7.0, 9.0, 10.0, 5.0, 4, 4, 1);
        $this->createWeapon(4, $this->axeArgs(1), 'Executioner Axe', 'crit', 7.0, 9.0, 10.0, 5.0, 4, 4, 1);
        $this->createWeapon(23, $this->twoHandedArgs(1), 'Executioner Battleaxe', 'crit', 9.1, 11.7, 13.0, 5.0, 4, 4, 1);
        $this->createDagger(26, 'Executioner Dagger', 'crit', 3.5, 4.5, 5.0, 5.0, 4, 4, 5, 20, 1);
    }

    private function createHybridItems(): void
    {
        $this->createWeapon(5, $this->swordArgs(1), 'Versatile Sword', 'hybrid', 8.5, 10.5, 4.5, 3.0, 6, 2, 1);
        $this->createWeapon(6, $this->axeArgs(1), 'Versatile Axe', 'hybrid', 8.5, 10.5, 4.5, 3.0, 6, 2, 1);
        $this->createWeapon(24, $this->twoHandedArgs(1), 'Versatile Battleaxe', 'hybrid', 11.05, 13.65, 5.5, 3.0, 6, 2, 1);
        $this->createDagger(27, 'Versatile Dagger', 'hybrid', 4.25, 5.25, 2.25, 3.0, 6, 2, 5, 20, 1);
    }

    private function createStableItemsLvl2(): void
    {
        $this->createWeapon(31, $this->swordArgs(2), 'Steadfast Sword II', 'stable', 13.5, 16.5, 0, 0, 12, 0, 2);
        $this->createWeapon(32, $this->axeArgs(2), 'Steadfast Axe II', 'stable', 13.5, 16.5, 0, 0, 12, 0, 2);
        $this->createWeapon(52, $this->twoHandedArgs(2), 'Steadfast Battleaxe II', 'stable', 17.55, 21.45, 0, 0, 12, 0, 2);
        $this->createDagger(55, 'Steadfast Dagger II', 'stable', 6.75, 8.25, 0, 0, 12, 0, 8, 30, 2);
    }

    private function createCritItemsLvl2(): void
    {
        $this->createWeapon(33, $this->swordArgs(2), 'Executioner Sword II', 'crit', 10.5, 13.5, 15.0, 5.0, 6, 6, 2);
        $this->createWeapon(34, $this->axeArgs(2), 'Executioner Axe II', 'crit', 10.5, 13.5, 15.0, 5.0, 6, 6, 2);
        $this->createWeapon(53, $this->twoHandedArgs(2), 'Executioner Battleaxe II', 'crit', 13.65, 17.55, 19.5, 5.0, 6, 6, 2);
        $this->createDagger(56, 'Executioner Dagger II', 'crit', 5.25, 6.75, 7.5, 5.0, 6, 6, 8, 30, 2);
    }

    private function createHybridItemsLvl2(): void
    {
        $this->createWeapon(35, $this->swordArgs(2), 'Versatile Sword II', 'hybrid', 12.75, 15.75, 6.75, 3.0, 9, 3, 2);
        $this->createWeapon(36, $this->axeArgs(2), 'Versatile Axe II', 'hybrid', 12.75, 15.75, 6.75, 3.0, 9, 3, 2);
        $this->createWeapon(54, $this->twoHandedArgs(2), 'Versatile Battleaxe II', 'hybrid', 16.575, 20.475, 8.25, 3.0, 9, 3, 2);
        $this->createDagger(57, 'Versatile Dagger II', 'hybrid', 6.375, 7.875, 3.375, 3.0, 9, 3, 8, 30, 2);
    }

    private function swordArgs(int $level = 1): array
    {
        $scale = $level === 2 ? 1.5 : 1.0;
        return [
            'damage_type' => 'slashing',
            'block_break_rating' => (int) round(20 * $scale),
            'pierce_multiplier' => 0.50,
            'max_damage_rating' => (int) round(75 * $scale),
        ];
    }

    private function axeArgs(int $level = 1): array
    {
        $scale = $level === 2 ? 1.5 : 1.0;
        return [
            'damage_type' => 'chopping',
            'block_break_rating' => (int) round(60 * $scale),
            'pierce_multiplier' => 0.65,
            'max_damage_rating' => 0,
        ];
    }

    private function twoHandedArgs(int $level = 1): array
    {
        $scale = $level === 2 ? 1.5 : 1.0;
        return [
            'damage_type' => 'chopping',
            'block_break_rating' => (int) round(120 * $scale),
            'pierce_multiplier' => 0.75,
            'max_damage_rating' => (int) round(75 * $scale),
            'is_two_handed' => true,
        ];
    }

    private function createWeapon($id, $baseArgs, $name, $arch, $min, $max, $flat, $cc, $requiredStrength = 4, $requiredWit = 0, $requiredLevel = 1)
    {
        $args = array_merge([
            'name' => $name,
            'type' => 'weapon',
            'archetype' => $arch,
            'min_damage' => $min,
            'max_damage' => $max,
            'flat_crit_bonus' => $flat,
            'crit_chance_bonus' => $cc,
            'accuracy_bonus' => 0.0,
            'required_strength' => $requiredStrength,
            'required_wit' => $requiredWit,
            'required_level' => $requiredLevel,
        ], $baseArgs);

        ItemModel::updateOrCreate(['id' => $id], $args);
    }

    private function createDagger($id, $name, $arch, $min, $max, $flat, $cc, $requiredStrength = 4, $requiredWit = 0, $parryRating = 5, $blockBreak = 20, $requiredLevel = 1)
    {
        ItemModel::updateOrCreate(
            ['id' => $id],
            [
                'name' => $name,
                'type' => 'offhand_weapon',
                'archetype' => $arch,
                'min_damage' => $min,
                'max_damage' => $max,
                'damage_type' => 'pierce',
                'parry_rating' => $parryRating,
                'block_break_rating' => $blockBreak,
                'flat_crit_bonus' => $flat,
                'crit_chance_bonus' => $cc,
                'required_strength' => $requiredStrength,
                'required_wit' => $requiredWit,
                'required_level' => $requiredLevel,
            ]
        );
    }

    private function createSeals(): void
    {
        ItemModel::updateOrCreate(['id' => 7], [
            'name' => 'Steadfast Seal', 'type' => 'seal', 'archetype' => 'stable',
            'min_damage' => 0.7, 'max_damage' => 0.8, 'flat_crit_bonus' => 0.0, 'crit_chance_bonus' => 0.0, 'required_strength' => 8, 'required_wit' => 0, 'required_level' => 1
        ]);
        ItemModel::updateOrCreate(['id' => 8], [
            'name' => 'Executioner Seal', 'type' => 'seal', 'archetype' => 'crit',
            'min_damage' => 0.5, 'max_damage' => 0.65, 'flat_crit_bonus' => 0.8, 'crit_chance_bonus' => 0.7, 'required_strength' => 4, 'required_wit' => 4, 'required_level' => 1
        ]);
        ItemModel::updateOrCreate(['id' => 9], [
            'name' => 'Versatile Seal', 'type' => 'seal', 'archetype' => 'hybrid',
            'min_damage' => 0.65, 'max_damage' => 0.7, 'flat_crit_bonus' => 0.4, 'crit_chance_bonus' => 0.6, 'required_strength' => 6, 'required_wit' => 2, 'required_level' => 1
        ]);
    }

    private function createSealsLvl2(): void
    {
        ItemModel::updateOrCreate(['id' => 37], [
            'name' => 'Steadfast Seal II', 'type' => 'seal', 'archetype' => 'stable',
            'min_damage' => 1.05, 'max_damage' => 1.2, 'flat_crit_bonus' => 0.0, 'crit_chance_bonus' => 0.0, 'required_strength' => 12, 'required_wit' => 0, 'required_level' => 2
        ]);
        ItemModel::updateOrCreate(['id' => 38], [
            'name' => 'Executioner Seal II', 'type' => 'seal', 'archetype' => 'crit',
            'min_damage' => 0.75, 'max_damage' => 0.975, 'flat_crit_bonus' => 1.2, 'crit_chance_bonus' => 0.7, 'required_strength' => 6, 'required_wit' => 6, 'required_level' => 2
        ]);
        ItemModel::updateOrCreate(['id' => 39], [
            'name' => 'Versatile Seal II', 'type' => 'seal', 'archetype' => 'hybrid',
            'min_damage' => 0.975, 'max_damage' => 1.05, 'flat_crit_bonus' => 0.6, 'crit_chance_bonus' => 0.6, 'required_strength' => 9, 'required_wit' => 3, 'required_level' => 2
        ]);
    }

    private function createShields(): void
    {
        ItemModel::updateOrCreate(['id' => 28], [
            'name' => 'Guardian Shield', 'type' => 'shield', 'archetype' => 'tank',
            'block_rating' => 40, 'ad_armor' => 2.0, 'dodge_bonus' => 0.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 8, 'required_level' => 1
        ]);
        ItemModel::updateOrCreate(['id' => 29], [
            'name' => 'Shadow Shield', 'type' => 'shield', 'archetype' => 'dodge',
            'block_rating' => 40, 'ad_armor' => 0.0, 'dodge_bonus' => 5.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 4, 'required_level' => 1
        ]);
        ItemModel::updateOrCreate(['id' => 30], [
            'name' => 'Balanced Shield', 'type' => 'shield', 'archetype' => 'universal',
            'block_rating' => 40, 'ad_armor' => 1.0, 'dodge_bonus' => 1.5, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 6, 'required_level' => 1
        ]);
    }

    private function createShieldsLvl2(): void
    {
        ItemModel::updateOrCreate(['id' => 58], [
            'name' => 'Guardian Shield II', 'type' => 'shield', 'archetype' => 'tank',
            'block_rating' => 60, 'ad_armor' => 3.0, 'dodge_bonus' => 0.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 12, 'required_level' => 2
        ]);
        ItemModel::updateOrCreate(['id' => 59], [
            'name' => 'Shadow Shield II', 'type' => 'shield', 'archetype' => 'dodge',
            'block_rating' => 60, 'ad_armor' => 0.0, 'dodge_bonus' => 5.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 6, 'required_level' => 2
        ]);
        ItemModel::updateOrCreate(['id' => 60], [
            'name' => 'Balanced Shield II', 'type' => 'shield', 'archetype' => 'universal',
            'block_rating' => 60, 'ad_armor' => 1.5, 'dodge_bonus' => 1.5, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 9, 'required_level' => 2
        ]);
    }

    private function createArmor(): void
    {
        $archetypes = [
            'Tank' => ['prefix' => 'Guardian', 'archetype' => 'tank', 'ad' => 6.00, 'dodge' => 0.0, 'con' => 8, 'dex' => 0, 'start_id' => 10],
            'Dodge' => ['prefix' => 'Shadow', 'archetype' => 'dodge', 'ad' => 0.00, 'dodge' => 12.0, 'con' => 4, 'dex' => 4, 'start_id' => 14],
            'Universal' => ['prefix' => 'Balanced', 'archetype' => 'universal', 'ad' => 4.00, 'dodge' => 2.5, 'con' => 6, 'dex' => 2, 'start_id' => 18],
        ];

        $subtypes = [
            'Helmet' => ArmorSubtype::HELMET,
            'Chestplate' => ArmorSubtype::BODY,
            'Boots' => ArmorSubtype::BOOTS,
            'Gauntlets' => ArmorSubtype::GLOVES,
        ];

        foreach ($archetypes as $data) {
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
                        'required_constitution' => $data['con'],
                        'required_dexterity' => $data['dex'],
                        'required_level' => 1,
                    ]
                );
                $offset++;
            }
        }
    }

    private function createArmorLvl2(): void
    {
        $archetypes = [
            'Tank' => ['prefix' => 'Guardian', 'archetype' => 'tank', 'ad' => 9.00, 'dodge' => 0.0, 'con' => 12, 'dex' => 0, 'start_id' => 40],
            'Dodge' => ['prefix' => 'Shadow', 'archetype' => 'dodge', 'ad' => 0.00, 'dodge' => 12.0, 'con' => 6, 'dex' => 6, 'start_id' => 44],
            'Universal' => ['prefix' => 'Balanced', 'archetype' => 'universal', 'ad' => 6.00, 'dodge' => 2.5, 'con' => 9, 'dex' => 3, 'start_id' => 48],
        ];

        $subtypes = [
            'Helmet' => ArmorSubtype::HELMET,
            'Chestplate' => ArmorSubtype::BODY,
            'Boots' => ArmorSubtype::BOOTS,
            'Gauntlets' => ArmorSubtype::GLOVES,
        ];

        foreach ($archetypes as $data) {
            $offset = 0;
            foreach ($subtypes as $suffix => $subtype) {
                ItemModel::updateOrCreate(
                    ['id' => $data['start_id'] + $offset],
                    [
                        'name' => "{$data['prefix']} {$suffix} II",
                        'type' => ItemType::ARMOR->value,
                        'archetype' => $data['archetype'],
                        'ad_armor' => $data['ad'],
                        'dodge_bonus' => $data['dodge'],
                        'armor_subtype' => $subtype->value,
                        'required_constitution' => $data['con'],
                        'required_dexterity' => $data['dex'],
                        'required_level' => 2,
                    ]
                );
                $offset++;
            }
        }
    }
}
