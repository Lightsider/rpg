<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Item;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\ArmorHydrator;
use App\Infrastructure\Eloquent\SealHydrator;
use App\Infrastructure\Eloquent\WeaponHydrator;
use Illuminate\Support\Facades\DB;

class BackpackService
{
    public function __construct(
        private readonly WeaponAssigner $weaponAssigner,
        private readonly WeaponHydrator $weaponHydrator,
        private readonly ArmorHydrator $armorHydrator,
        private readonly SealHydrator $sealHydrator,
        private readonly EquipmentService $equipmentService,
        private readonly CharacterStatService $statService
    ) {
    }

    public function ensureSeeded(CharacterModel $character): void
    {
        // MVP: Starting weapons/items disabled. Users must buy items from the shop.
        if ($character->backpack_seeded) {
            return;
        }

        $character->backpack_seeded = true;
        $character->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayload(CharacterModel $character): array
    {
        $items = CharacterItemModel::with('item')
            ->where('character_id', $character->id)
            ->orderByDesc('id')
            ->get();

        return $items
            ->map(function (CharacterItemModel $entry) {
                $item = $entry->item;
                if (!$item) {
                    return null;
                }

                return $this->itemPayload($item, (int) $entry->quantity);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayload(CharacterModel $character): array
    {
        return [
            EquipmentSlot::MAIN_HAND->value => $this->itemPayloadById($character->weapon_id),
            EquipmentSlot::OFF_HAND->value => $this->itemPayloadById($character->off_hand_id),
            EquipmentSlot::SEAL_1->value => $this->itemPayloadById($character->seal_1_id),
            EquipmentSlot::SEAL_2->value => $this->itemPayloadById($character->seal_2_id),
            EquipmentSlot::SEAL_3->value => $this->itemPayloadById($character->seal_3_id),
            EquipmentSlot::SEAL_4->value => $this->itemPayloadById($character->seal_4_id),
            EquipmentSlot::HELMET->value => $this->itemPayloadById($character->helmet_id),
            EquipmentSlot::CHEST->value => $this->itemPayloadById($character->chest_id),
            EquipmentSlot::LEGS->value => $this->itemPayloadById($character->legs_id),
            EquipmentSlot::GLOVES->value => $this->itemPayloadById($character->gloves_id),
        ];
    }

    public function equipItem(CharacterModel $character, int $itemId, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        $entry = CharacterItemModel::where('character_id', $character->id)
            ->where('item_id', $itemId)
            ->first();

        if (!$entry || $entry->quantity < 1) {
            throw new DomainException('Item is not in backpack.');
        }

        $item = ItemModel::find($itemId);
        if (!$item) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if ($item->type !== 'weapon') {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $weapon = $this->weaponHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($weapon, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $charDomain = $this->hydrateCharacterDomain($character);
            if (!$charDomain->canEquip($weapon)) {
                throw new DomainException('You do not meet the requirements for this weapon.');
            }

            DB::transaction(function () use ($character, $itemId, $oldMultiplier) {
                if ($character->weapon_id && (int) $character->weapon_id !== $itemId) {
                    $this->addToBackpack($character, (int) $character->weapon_id, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $character->weapon_id = $itemId;
                $character->weapon = $this->resolveLegacyWeaponName($itemId);
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if (!in_array($item->type, ['shield', 'offhand_weapon'], true)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            if ($item->type === 'offhand_weapon') {
                $weapon = $this->weaponHydrator->fromItem($item);
                if (!$this->equipmentService->isItemAllowedInSlot($weapon, $slotEnum)) {
                    throw new DomainException('Item cannot be equipped in that slot.');
                }

                $charDomain = $this->hydrateCharacterDomain($character);
                if (!$charDomain->canEquip($weapon)) {
                    throw new DomainException('You do not meet the requirements for this weapon.');
                }

                DB::transaction(function () use ($character, $itemId, $oldMultiplier) {
                    if ($character->off_hand_id && (int) $character->off_hand_id !== $itemId) {
                        $this->addToBackpack($character, (int) $character->off_hand_id, 1);
                    }

                    $this->removeFromBackpack($character, $itemId, 1);

                    $character->off_hand_id = $itemId;
                    $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                    $character->save();
                });

                return;
            }

            $shield = $this->armorHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($shield, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $charDomain = $this->hydrateCharacterDomain($character);
            if (!$charDomain->canEquip($shield)) {
                throw new DomainException('You do not meet the requirements for this shield.');
            }

            DB::transaction(function () use ($character, $itemId, $oldMultiplier) {
                if ($character->off_hand_id && (int) $character->off_hand_id !== $itemId) {
                    $this->addToBackpack($character, (int) $character->off_hand_id, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $character->off_hand_id = $itemId;
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::SEAL_1, EquipmentSlot::SEAL_2, EquipmentSlot::SEAL_3, EquipmentSlot::SEAL_4], true)) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if ($item->type !== 'seal') {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $seal = $this->sealHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($seal, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $charDomain = $this->hydrateCharacterDomain($character);
            if (!$charDomain->canEquip($seal)) {
                throw new DomainException('You do not meet the requirements for this seal.');
            }

            DB::transaction(function () use ($character, $itemId, $slotEnum, $oldMultiplier) {
                $currentSealId = $this->getSealSlotId($character, $slotEnum);
                if ($currentSealId && $currentSealId !== $itemId) {
                    $this->addToBackpack($character, $currentSealId, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $this->setSealSlotId($character, $slotEnum, $itemId);
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::HELMET, EquipmentSlot::CHEST, EquipmentSlot::LEGS, EquipmentSlot::GLOVES], true)) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if ($item->type !== 'armor') {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            if (!$this->armorSubtypeMatchesSlot($item->armor_subtype, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $armor = $this->armorHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($armor, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $charDomain = $this->hydrateCharacterDomain($character);
            if (!$charDomain->canEquip($armor)) {
                throw new DomainException('You do not meet the requirements for this armor.');
            }

            DB::transaction(function () use ($character, $itemId, $slotEnum, $armor, $oldMultiplier) {
                $currentArmorId = $this->getArmorSlotId($character, $slotEnum);
                if ($currentArmorId && $currentArmorId !== $itemId) {
                    $this->addToBackpack($character, $currentArmorId, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $this->setArmorSlotId($character, $slotEnum, $itemId);
                $this->setArmorValueForSlot($character, $slotEnum, $armor->getAdArmor());
                if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                    $this->recalculateArmArmor($character);
                }
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function unequipItem(CharacterModel $character, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if (!$character->weapon_id) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $oldMultiplier) {
                $this->addToBackpack($character, (int) $character->weapon_id, 1);

                $character->weapon_id = null;
                $character->weapon = null;
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            if (!$character->off_hand_id) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $oldMultiplier) {
                $this->addToBackpack($character, (int) $character->off_hand_id, 1);

                $character->off_hand_id = null;
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::SEAL_1, EquipmentSlot::SEAL_2, EquipmentSlot::SEAL_3, EquipmentSlot::SEAL_4], true)) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            $currentSealId = $this->getSealSlotId($character, $slotEnum);
            if (!$currentSealId) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $slotEnum, $currentSealId, $oldMultiplier) {
                $this->addToBackpack($character, $currentSealId, 1);
                $this->setSealSlotId($character, $slotEnum, null);
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::HELMET, EquipmentSlot::CHEST, EquipmentSlot::LEGS, EquipmentSlot::GLOVES], true)) {
            $oldMultiplier = $this->getMaxHpMultiplier($character);
            $currentArmorId = $this->getArmorSlotId($character, $slotEnum);
            if (!$currentArmorId) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $slotEnum, $currentArmorId, $oldMultiplier) {
                $this->addToBackpack($character, $currentArmorId, 1);
                $this->setArmorSlotId($character, $slotEnum, null);
                $this->setArmorValueForSlot($character, $slotEnum, 0.0);
                if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                    $this->recalculateArmArmor($character);
                }
                $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
                $character->save();
            });

            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function validateEquippedItems(CharacterModel $character): array
    {
        $charDomain = $this->hydrateCharacterDomain($character);
        $unequipped = [];

        foreach (EquipmentSlot::cases() as $slot) {
            $item = $charDomain->getEquipment()->getItem($slot);
            if ($item && !$charDomain->canEquip($item)) {
                $unequipped[] = $item->getName();
                $this->unequipItem($character, $slot->value);
            }
        }

        return $unequipped;
    }

    private function hydrateCharacterDomain(CharacterModel $model): \App\Domain\Character\Character
    {
        $weapon = null;
        if ($model->weapon_id) {
            $weaponItem = ItemModel::find($model->weapon_id);
            if ($weaponItem) {
                $weapon = $this->weaponHydrator->fromItem($weaponItem);
            }
        }

        $equipment = new \App\Domain\Equipment\Equipment();
        if ($weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        }

        if ($model->off_hand_id) {
            $offHandItem = ItemModel::find($model->off_hand_id);
            if ($offHandItem) {
                if (in_array($offHandItem->type, ['shield', 'armor', 'helmet', 'chest', 'legs', 'gloves'], true)) {
                    $equipment->setItem(EquipmentSlot::OFF_HAND, $this->armorHydrator->fromItem($offHandItem));
                } else {
                    $equipment->setItem(EquipmentSlot::OFF_HAND, $this->weaponHydrator->fromItem($offHandItem));
                }
            }
        }

        foreach ([1, 2, 3, 4] as $i) {
            $idField = "seal_{$i}_id";
            if ($model->$idField) {
                $item = ItemModel::find($model->$idField);
                if ($item) {
                    $slot = Constant("App\Domain\Equipment\EquipmentSlot::SEAL_{$i}");
                    $equipment->setItem($slot, $this->sealHydrator->fromItem($item));
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
            if ($model->$field) {
                $item = ItemModel::find($model->$field);
                if ($item) {
                    $equipment->setItem($slot, $this->armorHydrator->fromItem($item));
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
            locationId: (int) $model->location_id
        );
    }

    private function addToBackpack(CharacterModel $character, int $itemId, int $quantity): void
    {
        $entry = CharacterItemModel::firstOrNew([
            'character_id' => $character->id,
            'item_id' => $itemId,
        ]);

        $entry->quantity = (int) ($entry->quantity ?? 0) + $quantity;
        $entry->save();
    }

    private function removeFromBackpack(CharacterModel $character, int $itemId, int $quantity): void
    {
        $entry = CharacterItemModel::where('character_id', $character->id)
            ->where('item_id', $itemId)
            ->first();

        if (!$entry || $entry->quantity < $quantity) {
            throw new DomainException('Not enough items in backpack.');
        }

        $remaining = $entry->quantity - $quantity;
        if ($remaining <= 0) {
            $entry->delete();
            return;
        }

        $entry->quantity = $remaining;
        $entry->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ItemModel $item, int $quantity): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
            'quantity' => $quantity,
            'min_damage' => $item->min_damage,
            'max_damage' => $item->max_damage,
            'damage_type' => $item->damage_type,
            'accuracy_bonus' => $item->accuracy_bonus,
            'block_break_rating' => $item->block_break_rating,
            'pierce_multiplier' => $item->pierce_multiplier,
            'max_damage_rating' => $item->max_damage_rating,
            'archetype' => $item->archetype,
            'required_strength' => $item->required_strength,
            'required_wit' => $item->required_wit,
            'required_dexterity' => $item->required_dexterity,
            'required_constitution' => $item->required_constitution,
            'flat_crit_bonus' => $item->flat_crit_bonus,
            'crit_chance_bonus' => $item->crit_chance_bonus,
            'ad_armor' => $item->ad_armor,
            'dodge_bonus' => $item->dodge_bonus,
            'armor_subtype' => $item->armor_subtype,
            'defensive_ap_bonus' => $item->defensive_ap_bonus,
            'offhand_ap_bonus' => $item->type === 'offhand_weapon' ? 1 : 0,
            'parry_rating' => $item->parry_rating,
        ];
    }


    private function itemPayloadById(?int $itemId): ?array
    {
        if (!$itemId) {
            return null;
        }

        $item = ItemModel::find($itemId);
        return $item ? $this->itemPayload($item, 1) : null;
    }

    private function getSealSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return match ($slot) {
            EquipmentSlot::SEAL_1 => $character->seal_1_id ? (int) $character->seal_1_id : null,
            EquipmentSlot::SEAL_2 => $character->seal_2_id ? (int) $character->seal_2_id : null,
            EquipmentSlot::SEAL_3 => $character->seal_3_id ? (int) $character->seal_3_id : null,
            EquipmentSlot::SEAL_4 => $character->seal_4_id ? (int) $character->seal_4_id : null,
            default => null,
        };
    }

    private function setSealSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        match ($slot) {
            EquipmentSlot::SEAL_1 => $character->seal_1_id = $itemId,
            EquipmentSlot::SEAL_2 => $character->seal_2_id = $itemId,
            EquipmentSlot::SEAL_3 => $character->seal_3_id = $itemId,
            EquipmentSlot::SEAL_4 => $character->seal_4_id = $itemId,
            default => null,
        };
    }

    private function getArmorSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return match ($slot) {
            EquipmentSlot::HELMET => $character->helmet_id ? (int) $character->helmet_id : null,
            EquipmentSlot::CHEST => $character->chest_id ? (int) $character->chest_id : null,
            EquipmentSlot::LEGS => $character->legs_id ? (int) $character->legs_id : null,
            EquipmentSlot::GLOVES => $character->gloves_id ? (int) $character->gloves_id : null,
            default => null,
        };
    }

    private function setArmorSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        match ($slot) {
            EquipmentSlot::HELMET => $character->helmet_id = $itemId,
            EquipmentSlot::CHEST => $character->chest_id = $itemId,
            EquipmentSlot::LEGS => $character->legs_id = $itemId,
            EquipmentSlot::GLOVES => $character->gloves_id = $itemId,
            default => null,
        };
    }

    private function setArmorValueForSlot(CharacterModel $character, EquipmentSlot $slot, float $value): void
    {
        $value = (float) max(0, (int) round($value));
        match ($slot) {
            EquipmentSlot::HELMET => $character->ad_armor_head = $value,
            EquipmentSlot::CHEST => $character->ad_armor_chest = $value,
            EquipmentSlot::LEGS => $character->ad_armor_legs = $value,
            EquipmentSlot::GLOVES => $character->ad_armor_hands = $value,
            default => null,
        };
    }

    private function recalculateArmArmor(CharacterModel $character): void
    {
        $chestArmor = $this->getArmorValueById($character->chest_id);
        $glovesArmor = $this->getArmorValueById($character->gloves_id);
        $armValue = ($chestArmor * 0.5) + ($glovesArmor * 0.5);
        $armValue = (float) max(0, (int) round($armValue));
        $character->ad_armor_left_arm = $armValue;
        $character->ad_armor_right_arm = $armValue;
    }

    private function getArmorValueById(?int $itemId): float
    {
        if (!$itemId) {
            return 0.0;
        }

        $item = ItemModel::find($itemId);
        if (!$item) {
            return 0.0;
        }

        return (float) ($item->ad_armor ?? 0.0);
    }

    private function armorSubtypeMatchesSlot(?string $subtype, EquipmentSlot $slot): bool
    {
        if (!$subtype) {
            return false;
        }

        return match ($slot) {
            EquipmentSlot::HELMET => $subtype === 'helmet',
            EquipmentSlot::CHEST => $subtype === 'body',
            EquipmentSlot::LEGS => $subtype === 'boots',
            EquipmentSlot::GLOVES => $subtype === 'gloves',
            default => false,
        };
    }

    private function resolveLegacyWeaponName(int $weaponId): ?string
    {
        $weapon = ItemModel::query()
            ->where('id', $weaponId)
            ->where('type', 'weapon')
            ->first();

        if (!$weapon) {
            return null;
        }

        $name = strtolower((string) $weapon->name);
        return match ($name) {
            'sword' => 'sword',
            'axe' => 'axe',
            default => null,
        };
    }

    private function recalculateHpOnEquipmentChange(CharacterModel $character, float $oldMultiplier): void
    {
        $baseHp = $this->statService->calculateHp((int) ($character->constitution ?? 0));
        $newMultiplier = $this->getMaxHpMultiplier($character);

        $oldMax = (int) ceil($baseHp * (1.0 + $oldMultiplier));
        $newMax = (int) ceil($baseHp * (1.0 + $newMultiplier));

        $current = (int) ($character->hp ?? $baseHp);
        $newCurrent = $oldMax > 0
            ? (int) round(($current / $oldMax) * $newMax)
            : $newMax;

        $character->max_hp = $baseHp;
        $character->hp = (int) max(0, min($newMax, $newCurrent));
    }

    private function getMaxHpMultiplier(CharacterModel $character): float
    {
        $multiplier = 0.0;
        foreach ($this->getEquippedItemsForHp($character) as $item) {
            if (method_exists($item, 'getMaxHpMultiplier')) {
                $multiplier += $item->getMaxHpMultiplier();
            }
        }
        return $multiplier;
    }

    /**
     * @return array<int, Item>
     */
    private function getEquippedItemsForHp(CharacterModel $character): array
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
            $model = ItemModel::find($id);
            if (!$model) {
                continue;
            }
            $item = $this->hydrateItemForHp($model);
            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function hydrateItemForHp(ItemModel $item): ?Item
    {
        return match ($item->type) {
            'weapon', 'offhand_weapon' => $this->weaponHydrator->fromItem($item),
            'armor', 'helmet', 'chest', 'legs', 'gloves', 'shield' => $this->armorHydrator->fromItem($item),
            'seal' => $this->sealHydrator->fromItem($item),
            default => null,
        };
    }
}











