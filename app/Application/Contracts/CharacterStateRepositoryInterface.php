<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Domain\Equipment\EquipmentSlot;

interface CharacterStateRepositoryInterface
{
    public function findCharacterIdByUserId(int $userId): ?int;

    public function isBackpackSeeded(int $characterId): bool;

    public function markBackpackSeeded(int $characterId): void;

    /**
     * @return array<string, int|null>
     */
    public function getEquipmentItemIds(int $characterId): array;

    public function getEquipmentSlotItemId(int $characterId, EquipmentSlot $slot): ?int;

    public function setEquipmentSlotItemId(int $characterId, EquipmentSlot $slot, ?int $itemId): void;

    public function setMainHandWeapon(int $characterId, ?int $itemId, ?string $legacyWeaponName): void;

    public function setArmorValueForSlot(int $characterId, EquipmentSlot $slot, float $value): void;

    public function setArmArmorValues(int $characterId, float $leftArm, float $rightArm): void;

    /**
     * @return array{constitution:int, hp:int, max_hp:int}|null
     */
    public function getHpState(int $characterId): ?array;

    public function updateHpState(int $characterId, int $hp, int $maxHp): void;

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
    public function getCharacterEquipmentSnapshot(int $characterId): ?array;
}
