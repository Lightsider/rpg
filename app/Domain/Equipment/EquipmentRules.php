<?php

declare(strict_types=1);

namespace App\Domain\Equipment;

class EquipmentRules
{
    public function isSealSlot(EquipmentSlot $slot): bool
    {
        return in_array($slot, [
            EquipmentSlot::SEAL_1,
            EquipmentSlot::SEAL_2,
            EquipmentSlot::SEAL_3,
            EquipmentSlot::SEAL_4,
        ], true);
    }

    public function isArmorSlot(EquipmentSlot $slot): bool
    {
        return in_array($slot, [
            EquipmentSlot::HELMET,
            EquipmentSlot::CHEST,
            EquipmentSlot::LEGS,
            EquipmentSlot::GLOVES,
        ], true);
    }

    public function armorSubtypeMatchesSlot(?string $subtype, EquipmentSlot $slot): bool
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
}

