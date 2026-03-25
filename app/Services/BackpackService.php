<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\WeaponHydrator;
use Illuminate\Support\Facades\DB;

class BackpackService
{
    public function __construct(
        private readonly WeaponAssigner $weaponAssigner,
        private readonly WeaponHydrator $weaponHydrator,
        private readonly EquipmentService $equipmentService
    ) {
    }

    public function ensureSeeded(CharacterModel $character): void
    {
        if ($character->backpack_seeded) {
            return;
        }

        $this->weaponAssigner->ensureCharacterHasWeapon($character);

        $equippedWeaponId = $character->weapon_id;
        $weaponIds = ItemModel::query()
            ->where('type', 'weapon')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $extras = array_values(array_filter(
            $weaponIds,
            fn(int $id) => $equippedWeaponId === null || $id !== (int) $equippedWeaponId
        ));

        $extras = array_slice($extras, 0, 2);

        foreach ($extras as $itemId) {
            $this->addToBackpack($character, $itemId, 1);
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
        $weaponItem = null;
        if ($character->weapon_id) {
            $weaponItem = ItemModel::find($character->weapon_id);
        }

        return [
            EquipmentSlot::MAIN_HAND->value => $weaponItem ? $this->itemPayload($weaponItem, 1) : null,
        ];
    }

    public function equipItem(CharacterModel $character, int $itemId, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum || $slotEnum !== EquipmentSlot::MAIN_HAND) {
            throw new DomainException('Invalid equipment slot.');
        }

        $entry = CharacterItemModel::where('character_id', $character->id)
            ->where('item_id', $itemId)
            ->first();

        if (!$entry || $entry->quantity < 1) {
            throw new DomainException('Item is not in backpack.');
        }

        $item = ItemModel::find($itemId);
        if (!$item || $item->type !== 'weapon') {
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
    }

    public function unequipItem(CharacterModel $character, string $slot): void
    {
        $slotEnum = EquipmentSlot::tryFrom($slot);
        if (!$slotEnum || $slotEnum !== EquipmentSlot::MAIN_HAND) {
            throw new DomainException('Invalid equipment slot.');
        }

        if (!$character->weapon_id) {
            throw new DomainException('No item equipped in that slot.');
        }

        DB::transaction(function () use ($character) {
            $this->addToBackpack($character, (int) $character->weapon_id, 1);

            $character->weapon_id = null;
            $character->weapon = null;
            $character->save();
        });
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
            'flat_crit_bonus' => $item->flat_crit_bonus,
        ];
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


