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
        WeaponModel::updateOrCreate(
            ['name' => 'Wooden Sword'],
            [
                'min_damage' => 5,
                'max_damage' => 8,
                'damage_type' => DamageType::CRUSH,
                'accuracy_bonus' => 0.1,
                'block_break_chance' => 0.0,
            ]
        );

        WeaponModel::updateOrCreate(
            ['name' => 'Wooden Axe'],
            [
                'min_damage' => 5,
                'max_damage' => 8,
                'damage_type' => DamageType::CRUSH,
                'accuracy_bonus' => 0.0,
                'block_break_chance' => 0.15,
            ]
        );
    }
}
