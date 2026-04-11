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
        $this->createStableItems();
        $this->createCritItems();
        $this->createHybridItems();
        $this->createSeals();
        $this->createShields();
        $this->createArmor();
    }

    private function createStableItems(): void
    {
        $this->createWeapon(1, $this->swordArgs(), 'Steadfast Sword', 'stable', 9, 11, 0, 0);
        $this->createWeapon(2, $this->axeArgs(), 'Steadfast Axe', 'stable', 9, 11, 0, 0);
        $this->createWeapon(22, $this->twoHandedArgs(), 'Steadfast Battleaxe', 'stable', 11.7, 14.3, 0, 0);
        $this->createDagger(25, 'Steadfast Dagger', 'stable', 4.5, 5.5, 0, 0,8,0);
    }

    private function createCritItems(): void
    {
        $this->createWeapon(3, $this->swordArgs(), 'Executioner Sword', 'crit', 7.0, 9.0, 10.0, 5.0);
        $this->createWeapon(4, $this->axeArgs(), 'Executioner Axe', 'crit', 7.0, 9.0, 10.0, 5.0);
        $this->createWeapon(23, $this->twoHandedArgs(), 'Executioner Battleaxe', 'crit', 9.1, 11.7, 13.0, 5.0);
        $this->createDagger(26, 'Executioner Dagger', 'crit', 3.5, 4.5, 5.0, 5.0,4,4);
    }

    private function createHybridItems(): void
    {
        $this->createWeapon(5, $this->swordArgs(), 'Versatile Sword', 'hybrid', 8.5, 10.5, 4.5, 3.0);
        $this->createWeapon(6, $this->axeArgs(), 'Versatile Axe', 'hybrid', 8.5, 10.5, 4.5, 3.0);
        $this->createWeapon(24, $this->twoHandedArgs(), 'Versatile Battleaxe', 'hybrid', 11.05, 13.65, 5.5, 3.0);
        $this->createDagger(27, 'Versatile Dagger', 'hybrid', 4.25, 5.25, 2.25, 3.0,6,2);
    }

    private function swordArgs(): array
    {
        return ['damage_type' => 'slashing', 'block_break_rating' => 20, 'pierce_multiplier' => 0.50, 'max_damage_rating' => 75];
    }

    private function axeArgs(): array
    {
        return ['damage_type' => 'chopping', 'block_break_rating' => 60, 'pierce_multiplier' => 0.65, 'max_damage_rating' => 0];
    }

    private function twoHandedArgs(): array
    {
        return ['damage_type' => 'chopping', 'block_break_rating' => 120, 'pierce_multiplier' => 0.75, 'max_damage_rating' => 75, 'is_two_handed' => true];
    }

    private function createWeapon($id, $baseArgs, $name, $arch, $min, $max, $flat, $cc)
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
            'required_strength' => 4,
            'required_wit' => 0,
        ], $baseArgs);

        ItemModel::updateOrCreate(['id' => $id], $args);
    }

    private function createDagger($id, $name, $arch, $min, $max, $flat, $cc, $requiredStrenght = 4, $requiredWit = 0)
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
                'parry_rating' => 5,
                'block_break_rating' => 20,
                'flat_crit_bonus' => $flat,
                'crit_chance_bonus' => $cc,
                'required_strength' => $requiredStrenght,
                'required_wit' => $requiredWit,
            ]
        );
    }

    private function createSeals(): void
    {
        ItemModel::updateOrCreate(['id' => 7], [
            'name' => 'Steadfast Seal', 'type' => 'seal', 'archetype' => 'stable',
            'min_damage' => 0.7, 'max_damage' => 0.8, 'flat_crit_bonus' => 0.0, 'crit_chance_bonus' => 0.0, 'required_strength' => 4
        ]);
        ItemModel::updateOrCreate(['id' => 8], [
            'name' => 'Executioner Seal', 'type' => 'seal', 'archetype' => 'crit',
            'min_damage' => 0.5, 'max_damage' => 0.65, 'flat_crit_bonus' => 0.8, 'crit_chance_bonus' => 0.7, 'required_strength' => 4
        ]);
        ItemModel::updateOrCreate(['id' => 9], [
            'name' => 'Versatile Seal', 'type' => 'seal', 'archetype' => 'hybrid',
            'min_damage' => 0.65, 'max_damage' => 0.7, 'flat_crit_bonus' => 0.4, 'crit_chance_bonus' => 0.6, 'required_strength' => 4
        ]);
    }

    private function createShields(): void
    {
        ItemModel::updateOrCreate(['id' => 28], [
            'name' => 'Guardian Shield', 'type' => 'shield', 'archetype' => 'tank',
            'block_rating' => 40, 'ad_armor' => 2.0, 'dodge_bonus' => 0.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 8
        ]);
        ItemModel::updateOrCreate(['id' => 29], [
            'name' => 'Shadow Shield', 'type' => 'shield', 'archetype' => 'dodge',
            'block_rating' => 40, 'ad_armor' => 0.0, 'dodge_bonus' => 5.0, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 4
        ]);
        ItemModel::updateOrCreate(['id' => 30], [
            'name' => 'Balanced Shield', 'type' => 'shield', 'archetype' => 'universal',
            'block_rating' => 40, 'ad_armor' => 1.0, 'dodge_bonus' => 1.5, 'hp_multiplier' => 0.10, 'defensive_ap_bonus' => 1, 'required_constitution' => 6
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
                    ]
                );
                $offset++;
            }
        }
    }
}
