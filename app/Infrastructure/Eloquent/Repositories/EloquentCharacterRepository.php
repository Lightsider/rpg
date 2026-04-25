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
            'weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4',
            'helmet', 'chest', 'legs', 'gloves'
        ])
            ->where('id', $id)
            ->first();
        if (!$model) {
            return null;
        }

        return $this->mapModelToDomain($model);
    }

    public function findByUserId(int $userId): ?Character
    {
        $model = CharacterModel::with([
            'weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4',
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
            'level' => $character->getLevel(),
            'experience' => $character->getExperience(),
            'sublevel_index' => $character->getSublevelIndex(),
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
        CharacterModel::where('id', $id)->update(['hp' => $currentHp]);
    }

    public function updateCurrency(int $id, int $copper): void
    {
        CharacterModel::where('id', $id)->update(['currency_copper' => $copper]);
    }

    public function updateLocation(int $id, int $locationId): void
    {
        CharacterModel::where('id', $id)->update(['location_id' => $locationId]);
    }

    public function findByLocationId(int $locationId): array
    {
        $models = CharacterModel::with([
            'weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4',
            'helmet', 'chest', 'legs', 'gloves'
        ])
            ->where('location_id', $locationId)
            ->get();

        return $models->map(fn(CharacterModel $model) => $this->mapModelToDomain($model))->all();
    }

    public function findManyByIds(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        $models = CharacterModel::with([
            'weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4',
            'helmet', 'chest', 'legs', 'gloves'
        ])
            ->whereIn('id', $ids)
            ->get();

        return $models->map(fn(CharacterModel $model) => $this->mapModelToDomain($model))->all();
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

        if ($model->offHand) {
            if (in_array($model->offHand->type, ['shield', 'armor'], true)) {
                $offhand = $this->armorHydrator->fromItem($model->offHand);
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offhand);
            } elseif (in_array($model->offHand->type, ['offhand_weapon', 'weapon'], true)) {
                $offhand = $this->weaponHydrator->fromItem($model->offHand);
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offhand);
            }
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

        $chestArmor = (float) ($model->ad_armor_chest ?? 0.0);
        $handsArmor = (float) ($model->ad_armor_hands ?? 0.0);
        $armFallback = ($chestArmor * 0.5) + ($handsArmor * 0.5);
        $leftArm = $model->ad_armor_left_arm !== null ? (float) $model->ad_armor_left_arm : $armFallback;
        $rightArm = $model->ad_armor_right_arm !== null ? (float) $model->ad_armor_right_arm : $armFallback;

        return new Character(
            id: $model->id,
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
            adArmorChest: $chestArmor,
            adArmorLegs: (float)($model->ad_armor_legs ?? 0.0),
            adArmorLeftArm: $leftArm,
            adArmorRightArm: $rightArm,
            level: (int) ($model->level ?? 1),
            experience: (int) ($model->experience ?? 0),
            sublevelIndex: (int) ($model->sublevel_index ?? 0),
        );
    }
}
