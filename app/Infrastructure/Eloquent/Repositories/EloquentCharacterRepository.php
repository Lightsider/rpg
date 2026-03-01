<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\User as EloquentUser;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    private const int DEFAULT_WEAPON_ID = 0;
    private const int DEFAULT_MIN_DAMAGE = 5;
    private const int DEFAULT_MAX_DAMAGE = 10;
    private const float DEFAULT_ACCURACY_BONUS = 0.0;
    private const float DEFAULT_BLOCK_BREAK_CHANCE = 0.0;

    public function findById(int $id): ?Character
    {
        $model = EloquentUser::find($id);
        if (!$model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    public function updateHp(int $id, int $currentHp): void
    {
        EloquentUser::where('id', $id)->update(['hp' => $currentHp]);
    }

    private function mapToDomain(EloquentUser $model): Character
    {
        // For now, we create a default weapon based on the string in the database
        // This logic should ideally be in a WeaponRepository
        $weapon = $this->resolveWeapon($model->weapon);

        return new Character(
            id: $model->id,
            name: $model->name,
            strength: (int) $model->strength,
            agility: (int) $model->dexterity, // Mapping dexterity to agility
            constitution: (int) $model->constitution ?? 10,
            wit: (int) $model->wit ?? 10,
            maxHp: (int) $model->max_hp,
            currentHp: (int) $model->hp,
            weapon: $weapon,
            maxActionPoints: Character::DEFAULT_MAX_AP,
            currentActionPoints: Character::DEFAULT_MAX_AP,
            attackPointsUsed: 0,
            x: (int) $model->x,
            y: (int) $model->y
        );
    }

    private function resolveWeapon(?string $weaponType): Weapon
    {
        if ($weaponType === 'sword') {
            return new Weapon(
                1,
                'Sword',
                6,
                12,
                DamageType::SLASH,
                0.1, // Improved accuracy
                0.0
            );
        }

        if ($weaponType === 'axe') {
            return new Weapon(
                2,
                'Axe',
                8,
                14,
                DamageType::SLASH, // Using SLASH for now
                0.0,
                0.2 // Improved block break
            );
        }

        // Default unarmed/basic weapon
        return new Weapon(
            self::DEFAULT_WEAPON_ID,
            'Fists',
            self::DEFAULT_MIN_DAMAGE,
            self::DEFAULT_MAX_DAMAGE,
            DamageType::BLUNT,
            self::DEFAULT_ACCURACY_BONUS,
            self::DEFAULT_BLOCK_BREAK_CHANCE
        );
    }
}
