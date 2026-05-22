<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterEquipmentSlotStateService
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

    public function getSealSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($character, self::SEAL_SLOT_FIELDS, $slot);
    }

    public function setSealSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->setSlotItemId($character, self::SEAL_SLOT_FIELDS, $slot, $itemId);
    }

    public function getArmorSlotId(CharacterModel $character, EquipmentSlot $slot): ?int
    {
        return $this->getSlotItemId($character, self::ARMOR_SLOT_FIELDS, $slot);
    }

    public function setArmorSlotId(CharacterModel $character, EquipmentSlot $slot, ?int $itemId): void
    {
        $this->setSlotItemId($character, self::ARMOR_SLOT_FIELDS, $slot, $itemId);
    }

    public function setArmorValueForSlot(CharacterModel $character, EquipmentSlot $slot, float $value): void
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
}

