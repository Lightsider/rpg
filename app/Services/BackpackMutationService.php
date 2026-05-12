<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Contracts\TransactionInterface;
use App\Domain\Armor\Armor;
use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentRules;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;
use App\Domain\Item\Repositories\CharacterItemRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class BackpackMutationService
{
    /** @var array<string, string> */
    private const SEAL_SLOT_FIELDS = [
        EquipmentSlot::SEAL_1->value => 'seal_1_id',
        EquipmentSlot::SEAL_2->value => 'seal_2_id',
        EquipmentSlot::SEAL_3->value => 'seal_3_id',
        EquipmentSlot::SEAL_4->value => 'seal_4_id',
    ];

    /** @var array<string, string> */
    private const ARMOR_SLOT_FIELDS = [
        EquipmentSlot::HELMET->value => 'helmet_id',
        EquipmentSlot::CHEST->value => 'chest_id',
        EquipmentSlot::LEGS->value => 'legs_id',
        EquipmentSlot::GLOVES->value => 'gloves_id',
    ];

    /** @var array<string, string> */
    private const ARMOR_VALUE_FIELDS = [
        EquipmentSlot::HELMET->value => 'ad_armor_head',
        EquipmentSlot::CHEST->value => 'ad_armor_chest',
        EquipmentSlot::LEGS->value => 'ad_armor_legs',
        EquipmentSlot::GLOVES->value => 'ad_armor_hands',
    ];

    public function __construct(
        private readonly EquipmentService $equipmentService,
        private readonly EquipmentRules $equipmentRules,
        private readonly CharacterStatService $statService,
        private readonly TransactionInterface $transaction,
        private readonly CharacterLookupService $characterLookup,
        private readonly CharacterItemRepositoryInterface $characterItemRepository,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterEquipmentSnapshotService $snapshotService
    ) {
    }

    public function equipItem(CharacterModel $character, int $itemId, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        if ($this->characterItemRepository->getQuantity((int) $character->id, $itemId) < 1) {
            throw new DomainException('Item is not in backpack.');
        }

        $item = $this->itemRepository->findById($itemId);
        if (!$item) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $this->equipMainHand($character, $itemId, $item);
            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $this->equipOffHand($character, $itemId, $item, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isSealSlot($slotEnum)) {
            $this->equipSeal($character, $itemId, $item, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isArmorSlot($slotEnum)) {
            $this->equipArmor($character, $itemId, $item, $slotEnum);
            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function equipItemByCharacterId(int $characterId, int $itemId, string $slot): void
    {
        $this->equipItem($this->characterLookup->requireById($characterId), $itemId, $slot);
    }

    public function unequipItem(CharacterModel $character, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $this->unequipMainHand($character);
            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $this->unequipOffHand($character);
            return;
        }

        if ($this->equipmentRules->isSealSlot($slotEnum)) {
            $this->unequipSeal($character, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isArmorSlot($slotEnum)) {
            $this->unequipArmor($character, $slotEnum);
            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function unequipItemByCharacterId(int $characterId, string $slot): void
    {
        $this->unequipItem($this->characterLookup->requireById($characterId), $slot);
    }

    public function validateEquippedItems(CharacterModel $character): array
    {
        $charDomain = $this->snapshotService->buildCharacterDomain($character);
        $unequipped = [];

        foreach (EquipmentSlot::cases() as $slot) {
            $item = $charDomain->getEquipment()->getItem($slot);
            if ($item && !$charDomain->canEquip($item)) {
                $unequipped[] = $item->getName();
                $this->unequipItem($character, $slot->value);
            }
        }

        $mainHand = $charDomain->getEquipment()->getItem(EquipmentSlot::MAIN_HAND);
        $offHand = $charDomain->getEquipment()->getItem(EquipmentSlot::OFF_HAND);
        if ($mainHand instanceof Weapon && $mainHand->isTwoHanded() && $offHand) {
            $unequipped[] = $offHand->getName();
            $this->unequipItem($character, EquipmentSlot::OFF_HAND->value);
        }

        return $unequipped;
    }

    public function validateEquippedItemsByCharacterId(int $characterId): array
    {
        return $this->validateEquippedItems($this->characterLookup->requireById($characterId));
    }

    private function equipMainHand(CharacterModel $character, int $itemId, Item $item): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        if (!$item instanceof Weapon) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, EquipmentSlot::MAIN_HAND)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($character);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this weapon.');
        }

        $this->transaction->run(function () use ($character, $itemId, $oldMultiplier, $item) {
            if ($item->isTwoHanded()) {
                if ($character->off_hand_id !== null) {
                    throw new DomainException('Offhand must be empty to equip a 2-handed weapon.');
                }
                if ($character->weapon_id !== null) {
                    throw new DomainException('Main hand must be empty to equip a 2-handed weapon.');
                }
            }

            if ($character->weapon_id && (int) $character->weapon_id !== $itemId) {
                $this->addToBackpack($character, (int) $character->weapon_id, 1);
            }

            $this->removeFromBackpack($character, $itemId, 1);

            $character->weapon_id = $itemId;
            $character->weapon = $this->resolveLegacyWeaponName($itemId);
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function equipOffHand(CharacterModel $character, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);

        if ($character->weapon_id) {
            $mainHandItem = $this->itemRepository->findById((int) $character->weapon_id);
            if ($mainHandItem instanceof Weapon && $mainHandItem->isTwoHanded()) {
                throw new DomainException('Cannot equip offhand item when a 2-handed weapon is equipped.');
            }
        }

        if (!in_array($item->getItemType(), [ItemType::SHIELD, ItemType::OFFHAND_WEAPON], true)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if ($item instanceof Weapon) {
            if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
                throw new DomainException('Item cannot be equipped in that slot.');
            }

            $charDomain = $this->snapshotService->buildCharacterDomain($character);
            if (!$charDomain->canEquip($item)) {
                throw new DomainException('You do not meet the requirements for this weapon.');
            }

            $this->transaction->run(function () use ($character, $itemId, $oldMultiplier) {
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

        if (!$item instanceof Armor) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($character);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this shield.');
        }

        $this->transaction->run(function () use ($character, $itemId, $oldMultiplier) {
            if ($character->off_hand_id && (int) $character->off_hand_id !== $itemId) {
                $this->addToBackpack($character, (int) $character->off_hand_id, 1);
            }

            $this->removeFromBackpack($character, $itemId, 1);

            $character->off_hand_id = $itemId;
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function equipSeal(CharacterModel $character, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        if (!$item instanceof Seal) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($character);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this seal.');
        }

        $this->transaction->run(function () use ($character, $itemId, $slotEnum, $oldMultiplier) {
            $currentSealId = $this->getSealSlotId($character, $slotEnum);
            if ($currentSealId && $currentSealId !== $itemId) {
                $this->addToBackpack($character, $currentSealId, 1);
            }

            $this->removeFromBackpack($character, $itemId, 1);

            $this->setSealSlotId($character, $slotEnum, $itemId);
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function equipArmor(CharacterModel $character, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        if (!$item instanceof Armor) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentRules->armorSubtypeMatchesSlot($item->getSubtype()->value, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($character);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this armor.');
        }

        $this->transaction->run(function () use ($character, $itemId, $slotEnum, $item, $oldMultiplier) {
            $currentArmorId = $this->getArmorSlotId($character, $slotEnum);
            if ($currentArmorId && $currentArmorId !== $itemId) {
                $this->addToBackpack($character, $currentArmorId, 1);
            }

            $this->removeFromBackpack($character, $itemId, 1);

            $this->setArmorSlotId($character, $slotEnum, $itemId);
            $this->setArmorValueForSlot($character, $slotEnum, $item->getAdArmor());
            if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                $this->recalculateArmArmor($character);
            }
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function unequipMainHand(CharacterModel $character): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        if (!$character->weapon_id) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($character, $oldMultiplier) {
            $this->addToBackpack($character, (int) $character->weapon_id, 1);
            $character->weapon_id = null;
            $character->weapon = null;
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function unequipOffHand(CharacterModel $character): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        if (!$character->off_hand_id) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($character, $oldMultiplier) {
            $this->addToBackpack($character, (int) $character->off_hand_id, 1);
            $character->off_hand_id = null;
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function unequipSeal(CharacterModel $character, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        $currentSealId = $this->getSealSlotId($character, $slotEnum);
        if (!$currentSealId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($character, $slotEnum, $currentSealId, $oldMultiplier) {
            $this->addToBackpack($character, $currentSealId, 1);
            $this->setSealSlotId($character, $slotEnum, null);
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function unequipArmor(CharacterModel $character, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->getMaxHpMultiplier($character);
        $currentArmorId = $this->getArmorSlotId($character, $slotEnum);
        if (!$currentArmorId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($character, $slotEnum, $currentArmorId, $oldMultiplier) {
            $this->addToBackpack($character, $currentArmorId, 1);
            $this->setArmorSlotId($character, $slotEnum, null);
            $this->setArmorValueForSlot($character, $slotEnum, 0.0);
            if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                $this->recalculateArmArmor($character);
            }
            $this->recalculateHpOnEquipmentChange($character, $oldMultiplier);
            $character->save();
        });
    }

    private function addToBackpack(CharacterModel $character, int $itemId, int $quantity): void
    {
        $this->characterItemRepository->addToBackpack((int) $character->id, $itemId, $quantity);
    }

    private function removeFromBackpack(CharacterModel $character, int $itemId, int $quantity): void
    {
        $removed = $this->characterItemRepository->removeFromBackpack((int) $character->id, $itemId, $quantity);
        if (!$removed) {
            throw new DomainException('Not enough items in backpack.');
        }
    }

    private function getSealSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($character, self::SEAL_SLOT_FIELDS, $slot);
    }

    private function setSealSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->setSlotItemId($character, self::SEAL_SLOT_FIELDS, $slot, $itemId);
    }

    private function getArmorSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($character, self::ARMOR_SLOT_FIELDS, $slot);
    }

    private function setArmorSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->setSlotItemId($character, self::ARMOR_SLOT_FIELDS, $slot, $itemId);
    }

    private function setArmorValueForSlot(CharacterModel $character, EquipmentSlot $slot, float $value): void
    {
        $value = (float) max(0, (int) round($value));
        $slotField = self::ARMOR_VALUE_FIELDS[$slot->value] ?? null;
        if ($slotField === null) {
            return;
        }

        $character->{$slotField} = $value;
    }

    /**
     * @param array<string, string> $slotFields
     */
    private function getSlotItemId(CharacterModel $character, array $slotFields, EquipmentSlot $slot): ?int
    {
        $field = $slotFields[$slot->value] ?? null;
        if ($field === null) {
            return null;
        }

        $value = $character->{$field};
        return $value ? (int) $value : null;
    }

    /**
     * @param array<string, string> $slotFields
     */
    private function setSlotItemId(CharacterModel $character, array $slotFields, EquipmentSlot $slot, ?int $itemId): void
    {
        $field = $slotFields[$slot->value] ?? null;
        if ($field === null) {
            return;
        }

        $character->{$field} = $itemId;
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

        $item = $this->itemRepository->findById($itemId);
        return $item instanceof Armor ? (float) $item->getAdArmor() : 0.0;
    }

    private function resolveLegacyWeaponName(int $weaponId): ?string
    {
        $weapon = $this->itemRepository->findById($weaponId);
        if (!$weapon instanceof Weapon) {
            return null;
        }

        $name = strtolower((string) $weapon->getName());
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
        foreach ($this->snapshotService->getEquippedItems($character) as $item) {
            if (method_exists($item, 'getMaxHpMultiplier')) {
                $multiplier += $item->getMaxHpMultiplier();
            }
        }

        return $multiplier;
    }
}

