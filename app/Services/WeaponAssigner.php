<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;

class WeaponAssigner
{
    private const string SWORD_NAME = 'Sword';
    private const string AXE_NAME = 'Axe';

    public function ensureUserHasWeapon(User $user): void
    {
        if ($user->weapon_id !== null) {
            return;
        }

        $sword = ItemModel::firstOrCreate(
            ['name' => self::SWORD_NAME, 'type' => 'weapon'],
            [
                'min_damage' => 8,
                'max_damage' => 14,
                'damage_type' => 'slashing',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 20,
                'pierce_multiplier' => 0.50,
                'max_damage_rating' => 90,
            ]
        );

        $axe = ItemModel::firstOrCreate(
            ['name' => self::AXE_NAME, 'type' => 'weapon'],
            [
                'min_damage' => 8,
                'max_damage' => 14,
                'damage_type' => 'chopping',
                'accuracy_bonus' => 0.0,
                'block_break_rating' => 60,
                'pierce_multiplier' => 0.65,
                'max_damage_rating' => 0,
            ]
        );

        $chosen = ($user->id % 2 === 0) ? $axe : $sword;
        $legacy = $chosen->name === self::AXE_NAME ? 'axe' : 'sword';

        $user->update([
            'weapon_id' => $chosen->id,
            'weapon' => $legacy,
        ]);
    }
}
