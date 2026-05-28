<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Contracts\CharacterStateRepositoryInterface;
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

class BackpackMutationService
{
    public function __construct(
        private readonly EquipmentService $equipmentService,
        private readonly EquipmentRules $equipmentRules,
        private readonly TransactionInterface $transaction,
        private readonly CharacterItemRepositoryInterface $characterItemRepository,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterStateRepositoryInterface $characterStateRepository,
        private readonly CharacterEquipmentSnapshotService $snapshotService,
        private readonly CharacterEquipmentStatSyncService $statSyncService,
        private readonly CharacterEquipmentSlotStateService $slotStateService
    ) {
    }

    public function equipItemByCharacterId(int $characterId, int $itemId, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        if ($this->characterItemRepository->getQuantity($characterId, $itemId) < 1) {
            throw new DomainException('Item is not in backpack.');
        }

        $item = $this->itemRepository->findById($itemId);
        if (!$item) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $this->equipMainHand($characterId, $itemId, $item);
            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $this->equipOffHand($characterId, $itemId, $item, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isSealSlot($slotEnum)) {
            $this->equipSeal($characterId, $itemId, $item, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isArmorSlot($slotEnum)) {
            $this->equipArmor($characterId, $itemId, $item, $slotEnum);
            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function unequipItemByCharacterId(int $characterId, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum) {
            throw new DomainException('Invalid equipment slot.');
        }

        if ($slotEnum === EquipmentSlot::MAIN_HAND) {
            $this->unequipMainHand($characterId);
            return;
        }

        if ($slotEnum === EquipmentSlot::OFF_HAND) {
            $this->unequipOffHand($characterId);
            return;
        }

        if ($this->equipmentRules->isSealSlot($slotEnum)) {
            $this->unequipSeal($characterId, $slotEnum);
            return;
        }

        if ($this->equipmentRules->isArmorSlot($slotEnum)) {
            $this->unequipArmor($characterId, $slotEnum);
            return;
        }

        throw new DomainException('Invalid equipment slot.');
    }

    public function validateEquippedItemsByCharacterId(int $characterId): array
    {
        $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
        $unequipped = [];

        foreach (EquipmentSlot::cases() as $slot) {
            $item = $charDomain->getEquipment()->getItem($slot);
            if ($item && !$charDomain->canEquip($item)) {
                $unequipped[] = $item->getName();
                $this->unequipItemByCharacterId($characterId, $slot->value);
            }
        }

        $mainHand = $charDomain->getEquipment()->getItem(EquipmentSlot::MAIN_HAND);
        $offHand = $charDomain->getEquipment()->getItem(EquipmentSlot::OFF_HAND);
        if ($mainHand instanceof Weapon && $mainHand->isTwoHanded() && $offHand) {
            $unequipped[] = $offHand->getName();
            $this->unequipItemByCharacterId($characterId, EquipmentSlot::OFF_HAND->value);
        }

        return $unequipped;
    }

    private function equipMainHand(int $characterId, int $itemId, Item $item): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        if (!$item instanceof Weapon) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, EquipmentSlot::MAIN_HAND)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this weapon.');
        }

        $this->transaction->run(function () use ($characterId, $itemId, $oldMultiplier, $item) {
            $offHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND);
            $mainHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::MAIN_HAND);
            if ($item->isTwoHanded()) {
                if ($offHandId !== null) {
                    throw new DomainException('Offhand must be empty to equip a 2-handed weapon.');
                }
                if ($mainHandId !== null) {
                    throw new DomainException('Main hand must be empty to equip a 2-handed weapon.');
                }
            }

            if ($mainHandId && $mainHandId !== $itemId) {
                $this->addToBackpack($characterId, $mainHandId, 1);
            }

            $this->removeFromBackpack($characterId, $itemId, 1);

            $this->characterStateRepository->setMainHandWeapon($characterId, $itemId, $this->resolveLegacyWeaponName($itemId));
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function equipOffHand(int $characterId, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);

        if ($this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::MAIN_HAND)) {
            $mainHandItem = $this->snapshotService->getMainHandWeapon($characterId);
            if ($mainHandItem && $mainHandItem->isTwoHanded()) {
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

            $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
            if (!$charDomain->canEquip($item)) {
                throw new DomainException('You do not meet the requirements for this weapon.');
            }

            $this->transaction->run(function () use ($characterId, $itemId, $oldMultiplier) {
                $offHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND);
                if ($offHandId && $offHandId !== $itemId) {
                    $this->addToBackpack($characterId, $offHandId, 1);
                }

                $this->removeFromBackpack($characterId, $itemId, 1);

                $this->characterStateRepository->setEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND, $itemId);
                $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
            });

            return;
        }

        if (!$item instanceof Armor) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this shield.');
        }

        $this->transaction->run(function () use ($characterId, $itemId, $oldMultiplier) {
            $offHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND);
            if ($offHandId && $offHandId !== $itemId) {
                $this->addToBackpack($characterId, $offHandId, 1);
            }

            $this->removeFromBackpack($characterId, $itemId, 1);

            $this->characterStateRepository->setEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND, $itemId);
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function equipSeal(int $characterId, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        if (!$item instanceof Seal) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this seal.');
        }

        $this->transaction->run(function () use ($characterId, $itemId, $slotEnum, $oldMultiplier) {
            $currentSealId = $this->slotStateService->getSealSlotId($characterId, $slotEnum);
            if ($currentSealId && $currentSealId !== $itemId) {
                $this->addToBackpack($characterId, $currentSealId, 1);
            }

            $this->removeFromBackpack($characterId, $itemId, 1);

            $this->slotStateService->setSealSlotId($characterId, $slotEnum, $itemId);
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function equipArmor(int $characterId, int $itemId, Item $item, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        if (!$item instanceof Armor) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentRules->armorSubtypeMatchesSlot($item->getSubtype()->value, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        if (!$this->equipmentService->isItemAllowedInSlot($item, $slotEnum)) {
            throw new DomainException('Item cannot be equipped in that slot.');
        }

        $charDomain = $this->snapshotService->buildCharacterDomain($characterId);
        if (!$charDomain->canEquip($item)) {
            throw new DomainException('You do not meet the requirements for this armor.');
        }

        $this->transaction->run(function () use ($characterId, $itemId, $slotEnum, $item, $oldMultiplier) {
            $currentArmorId = $this->slotStateService->getArmorSlotId($characterId, $slotEnum);
            if ($currentArmorId && $currentArmorId !== $itemId) {
                $this->addToBackpack($characterId, $currentArmorId, 1);
            }

            $this->removeFromBackpack($characterId, $itemId, 1);

            $this->slotStateService->setArmorSlotId($characterId, $slotEnum, $itemId);
            $this->slotStateService->setArmorValueForSlot($characterId, $slotEnum, $item->getAdArmor());
            if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                $this->statSyncService->recalculateArmArmor($characterId);
            }
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function unequipMainHand(int $characterId): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        $mainHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::MAIN_HAND);
        if (!$mainHandId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($characterId, $mainHandId, $oldMultiplier) {
            $this->addToBackpack($characterId, $mainHandId, 1);
            $this->characterStateRepository->setMainHandWeapon($characterId, null, null);
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function unequipOffHand(int $characterId): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        $offHandId = $this->characterStateRepository->getEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND);
        if (!$offHandId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($characterId, $offHandId, $oldMultiplier) {
            $this->addToBackpack($characterId, $offHandId, 1);
            $this->characterStateRepository->setEquipmentSlotItemId($characterId, EquipmentSlot::OFF_HAND, null);
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function unequipSeal(int $characterId, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        $currentSealId = $this->slotStateService->getSealSlotId($characterId, $slotEnum);
        if (!$currentSealId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($characterId, $slotEnum, $currentSealId, $oldMultiplier) {
            $this->addToBackpack($characterId, $currentSealId, 1);
            $this->slotStateService->setSealSlotId($characterId, $slotEnum, null);
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function unequipArmor(int $characterId, EquipmentSlot $slotEnum): void
    {
        $oldMultiplier = $this->statSyncService->getMaxHpMultiplier($characterId);
        $currentArmorId = $this->slotStateService->getArmorSlotId($characterId, $slotEnum);
        if (!$currentArmorId) {
            throw new DomainException('No item equipped in that slot.');
        }

        $this->transaction->run(function () use ($characterId, $slotEnum, $currentArmorId, $oldMultiplier) {
            $this->addToBackpack($characterId, $currentArmorId, 1);
            $this->slotStateService->setArmorSlotId($characterId, $slotEnum, null);
            $this->slotStateService->setArmorValueForSlot($characterId, $slotEnum, 0.0);
            if (in_array($slotEnum, [EquipmentSlot::CHEST, EquipmentSlot::GLOVES], true)) {
                $this->statSyncService->recalculateArmArmor($characterId);
            }
            $this->statSyncService->recalculateHpOnEquipmentChange($characterId, $oldMultiplier);
        });
    }

    private function addToBackpack(int $characterId, int $itemId, int $quantity): void
    {
        $this->characterItemRepository->addToBackpack($characterId, $itemId, $quantity);
    }

    private function removeFromBackpack(int $characterId, int $itemId, int $quantity): void
    {
        $removed = $this->characterItemRepository->removeFromBackpack($characterId, $itemId, $quantity);
        if (!$removed) {
            throw new DomainException('Not enough items in backpack.');
        }
    }

    private function resolveLegacyWeaponName(int $weaponId): ?string
    {
        return $this->statSyncService->resolveLegacyWeaponName($weaponId);
    }
}
