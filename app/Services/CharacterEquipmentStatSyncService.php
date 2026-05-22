<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Armor\Armor;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterEquipmentStatSyncService
{
    public function __construct(
        private readonly CharacterStatService $statService,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly CharacterEquipmentSnapshotService $snapshotService
    ) {
    }

    public function getMaxHpMultiplier(CharacterModel $character): float
    {
        $multiplier = 0.0;
        foreach ($this->snapshotService->getEquippedItems($character) as $item) {
            if (method_exists($item, 'getMaxHpMultiplier')) {
                $multiplier += $item->getMaxHpMultiplier();
            }
        }

        return $multiplier;
    }

    public function recalculateHpOnEquipmentChange(CharacterModel $character, float $oldMultiplier): void
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

    public function recalculateArmArmor(CharacterModel $character): void
    {
        $chestArmor = $this->getArmorValueById($character->chest_id);
        $glovesArmor = $this->getArmorValueById($character->gloves_id);
        $armValue = ($chestArmor * 0.5) + ($glovesArmor * 0.5);
        $armValue = (float) max(0, (int) round($armValue));
        $character->ad_armor_left_arm = $armValue;
        $character->ad_armor_right_arm = $armValue;
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

