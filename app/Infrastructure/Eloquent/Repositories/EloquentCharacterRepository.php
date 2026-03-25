<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\WeaponHydrator;
use App\Infrastructure\Eloquent\SealHydrator;
use App\Infrastructure\Eloquent\ArmorHydrator;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator,
        private readonly SealHydrator $sealHydrator,
        private readonly ArmorHydrator $armorHydrator
    ) {
    }

    public function findById(int $id): ?Character
    {
        $model = CharacterModel::with([
            'weaponItem', 'seal1', 'seal2', 'seal3', 'seal4',
            'helmet', 'chest', 'legs', 'gloves'
        ])
            ->where('user_id', $id)
            ->first();
        if (!$model) {
            return null;
        }

        return $this->mapModelToDomain($model);
    }

    public function findByUserId(int $userId): ?Character
    {
        $model = CharacterModel::with([
            'weaponItem', 'seal1', 'seal2', 'seal3', 'seal4',
            'helmet', 'chest', 'legs', 'gloves'
        ])
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
        $weapon = null;
        if ($model->weaponItem) {
            $weapon = $this->weaponHydrator->fromItem($model->weaponItem);
        } elseif ($model->weapon) {
            $weapon = $this->weaponHydrator->fromLegacyName($model->weapon);
        }

        $equipment = new Equipment();
        if ($weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        }

        foreach ([1, 2, 3, 4] as $i) {
            $relation = "seal{$i}";
            if ($model->$relation) {
                $seal = $this->sealHydrator->fromItem($model->$relation);
                $slot = Constant("App\Domain\Equipment\EquipmentSlot::SEAL_{$i}");
                $equipment->setItem($slot, $seal);
            }
        }

        $armorRelations = [
            'helmet' => EquipmentSlot::HELMET,
            'chest' => EquipmentSlot::CHEST,
            'legs' => EquipmentSlot::LEGS,
            'gloves' => EquipmentSlot::GLOVES,
        ];

        foreach ($armorRelations as $relation => $slot) {
            if ($model->$relation) {
                $armor = $this->armorHydrator->fromItem($model->$relation);
                $equipment->setItem($slot, $armor);
            }
        }

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
            damageAccumulator: (float) ($model->damage_accumulator ?? 0.0),
            currencyCopper: (int) $model->currency_copper,
            locationId: $model->location_id,
            x: (int) $model->x,
            y: (int) $model->y,
            adArmorHead: (float)($model->ad_armor_head ?? 0.0),
            adArmorChest: (float)($model->ad_armor_chest ?? 0.0),
            adArmorLegs: (float)($model->ad_armor_legs ?? 0.0),
            adArmorHands: (float)($model->ad_armor_hands ?? 0.0),
        );
    }
}
