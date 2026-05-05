<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;

class BackpackReadService
{
    public function __construct(
        private readonly InventoryPayloadAssembler $payloadAssembler
    ) {
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

                return $this->payloadAssembler->fromItem($item, (int) $entry->quantity);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackpackPayloadByCharacterId(int $characterId): array
    {
        return $this->getBackpackPayload($this->requireCharacter($characterId));
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

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function getEquipmentPayloadByCharacterId(int $characterId): array
    {
        return $this->getEquipmentPayload($this->requireCharacter($characterId));
    }

    private function itemPayloadById(?int $itemId): ?array
    {
        if (!$itemId) {
            return null;
        }

        $item = ItemModel::find($itemId);
        return $item ? $this->payloadAssembler->fromItem($item, 1) : null;
    }

    private function requireCharacter(int $characterId): CharacterModel
    {
        $character = CharacterModel::find($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $character;
    }
}

