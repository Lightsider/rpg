<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Armor\Armor;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Item;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterEquipmentSnapshotService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository
    ) {
    }

    public function buildCharacterDomain(CharacterModel $model): \App\Domain\Character\Character
    {
        $equipment = new Equipment();

        $mainHand = $this->itemRepository->findById((int) ($model->weapon_id ?? 0));
        if ($mainHand instanceof Weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $mainHand);
        }

        $offHand = $this->itemRepository->findById((int) ($model->off_hand_id ?? 0));
        if ($offHand instanceof Armor || $offHand instanceof Weapon) {
            $equipment->setItem(EquipmentSlot::OFF_HAND, $offHand);
        }

        foreach ([1, 2, 3, 4] as $i) {
            $idField = "seal_{$i}_id";
            if ($model->{$idField}) {
                $item = $this->itemRepository->findById((int) $model->{$idField});
                if ($item instanceof Seal) {
                    $slot = constant("App\\Domain\\Equipment\\EquipmentSlot::SEAL_{$i}");
                    $equipment->setItem($slot, $item);
                }
            }
        }

        $armorFields = [
'helmet_id' => EquipmentSlot::HELMET,
'chest_id' => EquipmentSlot::CHEST,
'legs_id' => EquipmentSlot::LEGS,
'gloves_id' => EquipmentSlot::GLOVES,
        ];

        foreach ($armorFields as $field => $slot) {
            if ($model->{$field}) {
                $item = $this->itemRepository->findById((int) $model->{$field});
                if ($item instanceof Armor) {
                    $equipment->setItem($slot, $item);
                }
            }
        }

        return new \App\Domain\Character\Character(
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
            locationId: (int) $model->location_id,
            level: (int) ($model->level ?? 1),
        );
    }

    /**
     * @return array<int, Item>
     */
    public function getEquippedItems(CharacterModel $character): array
    {
        $ids = array_filter([
            $character->weapon_id,
            $character->off_hand_id,
            $character->seal_1_id,
            $character->seal_2_id,
            $character->seal_3_id,
            $character->seal_4_id,
            $character->helmet_id,
            $character->chest_id,
            $character->legs_id,
            $character->gloves_id,
        ], static fn ($id) => $id !== null);

        $items = [];
        foreach (array_unique($ids) as $id) {
            $item = $this->itemRepository->findById((int) $id);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
