<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterStateReadWriteRepository
{
    public function findCharacterIdByUserId(int $userId): ?int
    {
        $character = CharacterModel::query()
            ->where('user_id', $userId)
            ->first(['id']);

        return $character ? (int) $character->id : null;
    }

    public function isBackpackSeeded(int $characterId): bool
    {
        $character = CharacterModel::query()
            ->where('id', $characterId)
            ->first(['backpack_seeded']);

        return (bool) ($character?->backpack_seeded ?? false);
    }

    public function markBackpackSeeded(int $characterId): void
    {
        CharacterModel::query()
            ->where('id', $characterId)
            ->update(['backpack_seeded' => true]);
    }

    /**
     * @return array<string, int|null>
     */
    public function getEquipmentItemIds(int $characterId): array
    {
        $character = CharacterModel::query()
            ->where('id', $characterId)
            ->first([
                'weapon_id',
                'off_hand_id',
                'seal_1_id',
                'seal_2_id',
                'seal_3_id',
                'seal_4_id',
                'helmet_id',
                'chest_id',
                'legs_id',
                'gloves_id',
            ]);

        return [
            EquipmentSlot::MAIN_HAND->value => $character?->weapon_id !== null ? (int) $character->weapon_id : null,
            EquipmentSlot::OFF_HAND->value => $character?->off_hand_id !== null ? (int) $character->off_hand_id : null,
            EquipmentSlot::SEAL_1->value => $character?->seal_1_id !== null ? (int) $character->seal_1_id : null,
            EquipmentSlot::SEAL_2->value => $character?->seal_2_id !== null ? (int) $character->seal_2_id : null,
            EquipmentSlot::SEAL_3->value => $character?->seal_3_id !== null ? (int) $character->seal_3_id : null,
            EquipmentSlot::SEAL_4->value => $character?->seal_4_id !== null ? (int) $character->seal_4_id : null,
            EquipmentSlot::HELMET->value => $character?->helmet_id !== null ? (int) $character->helmet_id : null,
            EquipmentSlot::CHEST->value => $character?->chest_id !== null ? (int) $character->chest_id : null,
            EquipmentSlot::LEGS->value => $character?->legs_id !== null ? (int) $character->legs_id : null,
            EquipmentSlot::GLOVES->value => $character?->gloves_id !== null ? (int) $character->gloves_id : null,
        ];
    }
}

