<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Weapon\DamageType;
use App\Infrastructure\Persistence\WeaponModel;
use Illuminate\Database\Seeder;

class WeaponSeeder extends Seeder
{
    /**
     * Seed the weapons table.
     */
    public function run(): void
    {
        // Wooden Sword: Base damage 3-7
        WeaponModel::updateOrCreate(
            ['name' => 'Wooden Sword'],
            [
                'min_damage' => 3,
                'max_damage' => 7,
                'damage_type' => DamageType::SLASHING,
                'accuracy_bonus' => 0.0,
                'block_break_chance' => 0.20,
            ]
        );

        // Wooden Axe: Base damage 3-7
        WeaponModel::updateOrCreate(
            ['name' => 'Wooden Axe'],
            [
                'min_damage' => 3,
                'max_damage' => 7,
                'damage_type' => DamageType::CHOPPING,
                'accuracy_bonus' => 0.0,
                'block_break_chance' => 0.60,
            ]
        );
    }
}
