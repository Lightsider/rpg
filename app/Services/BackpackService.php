<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
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
        private readonly EquipmentService $equipmentService
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
            if ($item->type !== 'weapon') {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $weapon = $this->weaponHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($weapon, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $strength = (int) ($character->strength ?? 0);
            $wit = (int) ($character->wit ?? 0);
            if ($strength < $weapon->getRequiredStrength() || $wit < $weapon->getRequiredWit()) {
                throw new DomainException('You do not meet the requirements for this weapon.');
            }

            DB::transaction(function () use ($character, $itemId) {
                if ($character->weapon_id && (int) $character->weapon_id !== $itemId) {
                    $this->addToBackpack($character, (int) $character->weapon_id, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $character->weapon_id = $itemId;
                $character->weapon = $this->resolveLegacyWeaponName($itemId);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::SEAL_1, EquipmentSlot::SEAL_2, EquipmentSlot::SEAL_3, EquipmentSlot::SEAL_4], true)) {
            if ($item->type !== 'seal') {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $seal = $this->sealHydrator->fromItem($item);
            if (!$this->equipmentService->isItemAllowedInSlot($seal, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $strength = (int) ($character->strength ?? 0);
            $wit = (int) ($character->wit ?? 0);
            if ($strength < $seal->getRequiredStrength() || $wit < $seal->getRequiredWit()) {
                throw new DomainException('You do not meet the requirements for this seal.');
            }

            DB::transaction(function () use ($character, $itemId, $slotEnum) {
                $currentSealId = $this->getSealSlotId($character, $slotEnum);
                if ($currentSealId && $currentSealId !== $itemId) {
                    $this->addToBackpack($character, $currentSealId, 1);
                }

                $this->removeFromBackpack($character, $itemId, 1);

                $this->setSealSlotId($character, $slotEnum, $itemId);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::HELMET, EquipmentSlot::CHEST, EquipmentSlot::LEGS, EquipmentSlot::GLOVES], true)) {
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

            $strength = (int) ($character->strength ?? 0);
            $wit = (int) ($character->wit ?? 0);
            $dexterity = (int) ($character->dexterity ?? 0);
            $constitution = (int) ($character->constitution ?? 0);

            if ($strength < $armor->getRequiredStrength() ||
                $wit < $armor->getRequiredWit() ||
                $dexterity < $armor->getRequiredDexterity() ||
                $constitution < $armor->getRequiredConstitution()
            ) {
                throw new DomainException('You do not meet the requirements for this armor.');
            }

            DB::transaction(function () use ($character, $itemId, $slotEnum, $armor) {
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
            if (!$character->weapon_id) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character) {
                $this->addToBackpack($character, (int) $character->weapon_id, 1);

                $character->weapon_id = null;
                $character->weapon = null;
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::SEAL_1, EquipmentSlot::SEAL_2, EquipmentSlot::SEAL_3, EquipmentSlot::SEAL_4], true)) {
            $currentSealId = $this->getSealSlotId($character, $slotEnum);
            if (!$currentSealId) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $slotEnum, $currentSealId) {
                $this->addToBackpack($character, $currentSealId, 1);
                $this->setSealSlotId($character, $slotEnum, null);
                $character->save();
            });

            return;
        }

        if (in_array($slotEnum, [EquipmentSlot::HELMET, EquipmentSlot::CHEST, EquipmentSlot::LEGS, EquipmentSlot::GLOVES], true)) {
            $currentArmorId = $this->getArmorSlotId($character, $slotEnum);
            if (!$currentArmorId) {
                throw new DomainException('No item equipped in that slot.');
            }

            DB::transaction(function () use ($character, $slotEnum, $currentArmorId) {
                $this->addToBackpack($character, $currentArmorId, 1);
                $this->setArmorSlotId($character, $slotEnum, null);
                $this->setArmorValueForSlot($character, $slotEnum, 0.0);
                if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                    $this->recalculateArmArmor($character);
                }
                $character->save();
            });

            return;
        }

        throw new DomainException('Invalid equipment slot.');
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
            'ad_armor' => $item->ad_armor,
            'dodge_bonus' => $item->dodge_bonus,
            'armor_subtype' => $item->armor_subtype,
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
}











