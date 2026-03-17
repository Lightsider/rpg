<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\User as EloquentUser;
use App\Infrastructure\Eloquent\Models\ItemModel;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    public function findById(int $id): ?Character
    {
        $model = CharacterModel::with('user.weaponItem')->find($id);
        if (!$model) {
            return null;
        }

        return $this->mapModelToDomain($model);
    }

    public function findByUserId(int $userId): ?Character
    {
        $model = CharacterModel::with('user.weaponItem')
            ->where('user_id', $userId)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->mapModelToDomain($model);
    }

    public function create(Character $character): Character
    {
        $model = CharacterModel::create([
            'user_id' => $character->getUserId(),
            'name' => $character->getName(),
            'hp' => $character->getCurrentHp(),
            'max_hp' => $character->getMaxHp(),
            'location_id' => $character->getLocationId()
        ]);

        return $this->mapModelToDomain($model);
    }

    public function updateHp(int $id, int $currentHp): void
    {
        CharacterModel::where('id', $id)->update(['hp' => $currentHp]);
    }

    private function mapModelToDomain(CharacterModel $model): Character
    {
        $userModel = $model->user;
        $weapon = $userModel && $userModel->weaponItem
            ? $this->resolveWeaponFromItem($userModel->weaponItem)
            : $this->resolveWeaponByLegacyName($userModel ? $userModel->weapon : null);

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $model->user_id,
            userId: $model->user_id,
            name: $model->name,
            strength: (int) ($userModel->strength ?? 10),
            agility: (int) ($userModel->dexterity ?? 10),
            constitution: (int) ($userModel->constitution ?? 10),
            wit: (int) ($userModel->wit ?? 10),
            maxHp: (int) $model->max_hp,
            currentHp: (int) $model->hp,
            equipment: $equipment,
            locationId: $model->location_id,
        );
    }

    private function resolveWeaponFromItem(ItemModel $item): Weapon
    {
        return new Weapon(
            id: $item->id,
            name: $item->name,
            minDamage: $item->min_damage,
            maxDamage: $item->max_damage,
            damageType: DamageType::from(strtolower($item->damage_type ?? 'blunt')),
            accuracyBonus: $item->accuracy_bonus,
            blockBreakRating: $item->block_break_rating,
            pierceMultiplier: $item->pierce_multiplier,
            maxDamageRating: $item->max_damage_rating
        );
    }

    private function resolveWeaponByLegacyName(?string $weaponType): Weapon
    {
        // Fallback - matching WeaponSeeder values
        if ($weaponType === 'sword') {
            return new Weapon(1, 'Sword', 3, 7, DamageType::SLASHING, 0.0, 20, 0.50, 90);
        }

        if ($weaponType === 'axe') {
            return new Weapon(2, 'Axe', 3, 7, DamageType::CHOPPING, 0.0, 60, 0.65);
        }

        return new Weapon(0, 'Fists', 1, 3, DamageType::BLUNT, 0.0, 0, 0.10);
    }
}
