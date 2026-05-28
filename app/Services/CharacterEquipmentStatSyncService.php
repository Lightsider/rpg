<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Contracts\CharacterStateRepositoryInterface;
use App\Domain\Armor\Armor;
use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Weapon\Weapon;

class CharacterEquipmentStatSyncService
{
    public function __construct(
        private readonly CharacterStatService $statService,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterEquipmentSnapshotService $snapshotService,
        private readonly CharacterStateRepositoryInterface $characterStateRepository
    ) {
    }

    public function getMaxHpMultiplier(int $characterId): float
    {
        $multiplier = 0.0;
        foreach ($this->snapshotService->getEquippedItems($characterId) as $item) {
            if (method_exists($item, 'getMaxHpMultiplier')) {
                $multiplier += $item->getMaxHpMultiplier();
            }
        }

        return $multiplier;
    }

    public function recalculateHpOnEquipmentChange(int $characterId, float $oldMultiplier): void
    {
        $hpState = $this->characterStateRepository->getHpState($characterId);
        if ($hpState === null) {
            throw new DomainException('Character not found.');
        }

        $baseHp = $this->statService->calculateHp($hpState['constitution']);
        $newMultiplier = $this->getMaxHpMultiplier($characterId);

        $oldMax = (int) ceil($baseHp * (1.0 + $oldMultiplier));
        $newMax = (int) ceil($baseHp * (1.0 + $newMultiplier));

        $current = $hpState['hp'];
        $newCurrent = $oldMax > 0
            ? (int) round(($current / $oldMax) * $newMax)
            : $newMax;

        $this->characterStateRepository->updateHpState(
            $characterId,
            (int) max(0, min($newMax, $newCurrent)),
            $baseHp
        );
    }

    public function recalculateArmArmor(int $characterId): void
    {
        $itemIds = $this->characterStateRepository->getEquipmentItemIds($characterId);
        $chestArmor = $this->getArmorValueById($itemIds[EquipmentSlot::CHEST->value]);
        $glovesArmor = $this->getArmorValueById($itemIds[EquipmentSlot::GLOVES->value]);
        $armValue = ($chestArmor * 0.5) + ($glovesArmor * 0.5);
        $this->characterStateRepository->setArmArmorValues($characterId, $armValue, $armValue);
    }

    public function resolveLegacyWeaponName(int $weaponId): ?string
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

    private function getArmorValueById(?int $itemId): float
    {
        if (!$itemId) {
            return 0.0;
        }

        $item = $this->itemRepository->findById($itemId);
        return $item instanceof Armor ? (float) $item->getAdArmor() : 0.0;
    }
}
