<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Application\Contracts\CharacterStateRepositoryInterface;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class CharacterStateReadWriteRepository implements CharacterStateRepositoryInterface
{
    /** @var array<string, string> */
    private const SLOT_FIELDS = [
        EquipmentSlot::MAIN_HAND->value => 'weapon_id',
        EquipmentSlot::OFF_HAND->value => 'off_hand_id',
        EquipmentSlot::SEAL_1->value => 'seal_1_id',
        EquipmentSlot::SEAL_2->value => 'seal_2_id',
        EquipmentSlot::SEAL_3->value => 'seal_3_id',
        EquipmentSlot::SEAL_4->value => 'seal_4_id',
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

    public function setEquipmentSlotItemId(int $characterId, EquipmentSlot $slot, ?int $itemId): void
    {
        $field = self::SLOT_FIELDS[$slot->value] ?? null;
        if ($field === null) {
            return;
        }

        CharacterModel::query()
            ->where('id', $characterId)
            ->update([$field => $itemId]);
    }

    public function getEquipmentSlotItemId(int $characterId, EquipmentSlot $slot): ?int
    {
        $itemIds = $this->getEquipmentItemIds($characterId);

        return $itemIds[$slot->value] ?? null;
    }

    public function setMainHandWeapon(int $characterId, ?int $itemId, ?string $legacyWeaponName): void
    {
        CharacterModel::query()
            ->where('id', $characterId)
            ->update([
                'weapon_id' => $itemId,
                'weapon' => $legacyWeaponName,
            ]);
    }

    public function setArmorValueForSlot(int $characterId, EquipmentSlot $slot, float $value): void
    {
        $field = self::ARMOR_VALUE_FIELDS[$slot->value] ?? null;
        if ($field === null) {
            return;
        }

        CharacterModel::query()
            ->where('id', $characterId)
            ->update([$field => (float) max(0, (int) round($value))]);
    }

    public function setArmArmorValues(int $characterId, float $leftArm, float $rightArm): void
    {
        CharacterModel::query()
            ->where('id', $characterId)
            ->update([
                'ad_armor_left_arm' => (float) max(0, (int) round($leftArm)),
                'ad_armor_right_arm' => (float) max(0, (int) round($rightArm)),
            ]);
    }

    /**
     * @return array{constitution:int, hp:int, max_hp:int}|null
     */
    public function getHpState(int $characterId): ?array
    {
        $character = CharacterModel::query()
            ->where('id', $characterId)
            ->first(['constitution', 'hp', 'max_hp']);

        if (!$character) {
            return null;
        }

        return [
            'constitution' => (int) $character->constitution,
            'hp' => (int) $character->hp,
            'max_hp' => (int) $character->max_hp,
        ];
    }

    public function updateHpState(int $characterId, int $hp, int $maxHp): void
    {
        CharacterModel::query()
            ->where('id', $characterId)
            ->update([
                'hp' => $hp,
                'max_hp' => $maxHp,
            ]);
    }

    /**
     * @return array{
     *   id:int,
     *   user_id:int,
     *   name:string,
     *   strength:int,
     *   dexterity:int,
     *   constitution:int,
     *   wit:int,
     *   max_hp:int,
     *   hp:int,
     *   location_id:int,
     *   level:int,
     *   equipment:array<string, int|null>
     * }|null
     */
    public function getCharacterEquipmentSnapshot(int $characterId): ?array
    {
        $character = CharacterModel::query()
            ->where('id', $characterId)
            ->first([
                'id',
                'user_id',
                'name',
                'strength',
                'dexterity',
                'constitution',
                'wit',
                'max_hp',
                'hp',
                'location_id',
                'level',
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

        if (!$character) {
            return null;
        }

        return [
            'id' => (int) $character->id,
            'user_id' => (int) $character->user_id,
            'name' => (string) $character->name,
            'strength' => (int) $character->strength,
            'dexterity' => (int) $character->dexterity,
            'constitution' => (int) $character->constitution,
            'wit' => (int) $character->wit,
            'max_hp' => (int) $character->max_hp,
            'hp' => (int) $character->hp,
            'location_id' => (int) $character->location_id,
            'level' => (int) ($character->level ?? 1),
            'equipment' => [
                EquipmentSlot::MAIN_HAND->value => $character->weapon_id !== null ? (int) $character->weapon_id : null,
                EquipmentSlot::OFF_HAND->value => $character->off_hand_id !== null ? (int) $character->off_hand_id : null,
                EquipmentSlot::SEAL_1->value => $character->seal_1_id !== null ? (int) $character->seal_1_id : null,
                EquipmentSlot::SEAL_2->value => $character->seal_2_id !== null ? (int) $character->seal_2_id : null,
                EquipmentSlot::SEAL_3->value => $character->seal_3_id !== null ? (int) $character->seal_3_id : null,
                EquipmentSlot::SEAL_4->value => $character->seal_4_id !== null ? (int) $character->seal_4_id : null,
                EquipmentSlot::HELMET->value => $character->helmet_id !== null ? (int) $character->helmet_id : null,
                EquipmentSlot::CHEST->value => $character->chest_id !== null ? (int) $character->chest_id : null,
                EquipmentSlot::LEGS->value => $character->legs_id !== null ? (int) $character->legs_id : null,
                EquipmentSlot::GLOVES->value => $character->gloves_id !== null ? (int) $character->gloves_id : null,
            ],
        ];
    }
}
