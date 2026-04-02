<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\CharacterModel;

class WeaponAssigner
{
    public function ensureCharacterHasWeapon(CharacterModel $character): void
    {
        if ($character->weapon_id !== null) {
            return;
        }

        // Find appropriate weapon ID based on character stats
        $strength = (int) ($character->strength ?? 10);
        $wit = (int) ($character->wit ?? 10);

        if ($strength >= 10) {
            $weaponId = ($character->user_id % 2 === 0) ? 2 : 1; // Steadfast Axe/Sword
        } elseif ($wit >= 5) {
            $weaponId = ($character->user_id % 2 === 0) ? 4 : 3; // Executioner Axe/Sword
        } else {
            $weaponId = ($character->user_id % 2 === 0) ? 6 : 5; // Versatile Axe/Sword
        }

        $character->update([
            'weapon_id' => $weaponId,
        ]);
    }
}
