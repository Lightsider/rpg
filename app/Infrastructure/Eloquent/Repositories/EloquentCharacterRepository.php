<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\WeaponHydrator;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator
    ) {
    }

    public function findById(int $id): ?Character
    {
        $model = CharacterModel::with('weaponItem')
            ->where('user_id', $id)
            ->first();
        if (!$model) {
            return null;
        }

        return $this->mapModelToDomain($model);
    }

    public function findByUserId(int $userId): ?Character
    {
        $model = CharacterModel::with('weaponItem')
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
            'strength' => $character->getStrength(),
            'dexterity' => $character->getAgility(),
            'constitution' => $character->getConstitution(),
            'wit' => $character->getWit(),
            'hp' => $character->getCurrentHp(),
            'max_hp' => $character->getMaxHp(),
            'location_id' => $character->getLocationId(),
            'x' => $character->getX(),
            'y' => $character->getY(),
            'currency_copper' => $character->getCurrencyCopper(),
            'weapon_id' => null,
            'weapon' => null,
        ]);

        return $this->mapModelToDomain($model);
    }

    public function updateHp(int $id, int $currentHp): void
    {
        CharacterModel::where('user_id', $id)->update(['hp' => $currentHp]);
    }

    public function updateCurrency(int $id, int $copper): void
    {
        CharacterModel::where('user_id', $id)->update(['currency_copper' => $copper]);
    }

    private function mapModelToDomain(CharacterModel $model): Character
    {
        $weapon = $model->weaponItem
            ? $this->weaponHydrator->fromItem($model->weaponItem)
            : $this->weaponHydrator->fromLegacyName($model->weapon);

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $model->user_id,
            userId: $model->user_id,
            name: $model->name,
            strength: (int) $model->strength,
            agility: (int) $model->dexterity,
            constitution: (int) $model->constitution,
            wit: (int) $model->wit,
            maxHp: (int) $model->max_hp,
            currentHp: (int) $model->hp,
            equipment: $equipment,
            currencyCopper: (int) $model->currency_copper,
            locationId: $model->location_id,
            x: (int) $model->x,
            y: (int) $model->y,
        );
    }
}
