<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;

class WeaponAssigner
{
    private const string SWORD_NAME = 'Balanced Sword';
    private const string AXE_NAME = 'Balanced Axe';

    public function ensureUserHasWeapon(User $user): void
    {
        if ($user->weapon_id !== null) {
            return;
        }

        // Find appropriate weapon ID based on user stats
        $strength = (int) ($user->strength ?? 10);
        $wit = (int) ($user->wit ?? 10);

        if ($strength >= 10) {
            $weaponId = ($user->id % 2 === 0) ? 2 : 1; // Guardian Axe/Sword
        } elseif ($wit >= 5) {
            $weaponId = ($user->id % 2 === 0) ? 4 : 3; // Executioner Axe/Sword
        } else {
            $weaponId = ($user->id % 2 === 0) ? 6 : 5; // Balanced Axe/Sword
        }

        $user->update([
            'weapon_id' => $weaponId,
        ]);
    }
}
