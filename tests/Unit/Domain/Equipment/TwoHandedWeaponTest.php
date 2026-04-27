<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Equipment;

use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\Dagger;
use Tests\TestCase;

class TwoHandedWeaponTest extends TestCase
{
    private EquipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $allowedTypes = [
            'main_hand' => ['weapon'],
            'off_hand' => ['shield', 'offhand_weapon'],
        ];

        $this->service = new EquipmentService($allowedTypes);
    }

    private function createCharacter(): Character
    {
        return new Character(
            id: 1,
            userId: 1,
            name: "Hero",
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: 100,
            equipment: new Equipment()
        );
    }

    public function test_cannot_equip_offhand_when_2h_weapon_is_equipped(): void
    {
        $character = $this->createCharacter();
        $weapon2h = new Weapon(1, "Great Axe", 10, 20, DamageType::CHOPPING, 0.9, 0, 0.0, 0, \App\Domain\Weapon\WeaponArchetype::STABLE, 0, 0, 0, 0.0, true);
        $dagger = new Dagger(2, "Dagger", 5, 10);

        $this->service->equipItem($character, $weapon2h, EquipmentSlot::MAIN_HAND);
        
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot equip offhand item when a 2-handed weapon is equipped.");

        $this->service->equipItem($character, $dagger, EquipmentSlot::OFF_HAND);
    }

    public function test_cannot_equip_2h_weapon_when_offhand_is_not_empty(): void
    {
        $character = $this->createCharacter();
        $weapon2h = new Weapon(1, "Great Axe", 10, 20, DamageType::CHOPPING, 0.9, 0, 0.0, 0, \App\Domain\Weapon\WeaponArchetype::STABLE, 0, 0, 0, 0.0, true);
        $dagger = new Dagger(2, "Dagger", 5, 10);

        $this->service->equipItem($character, $dagger, EquipmentSlot::OFF_HAND);
        $this->assertNotNull($character->getEquipment()->getItem(EquipmentSlot::OFF_HAND));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Offhand must be empty to equip a 2-handed weapon.");

        $this->service->equipItem($character, $weapon2h, EquipmentSlot::MAIN_HAND);
    }

    public function test_cannot_equip_2h_weapon_when_mainhand_is_not_empty(): void
    {
        $character = $this->createCharacter();
        $weapon2h = new Weapon(1, "Great Axe", 10, 20, DamageType::CHOPPING, 0.9, 0, 0.0, 0, \App\Domain\Weapon\WeaponArchetype::STABLE, 0, 0, 0, 0.0, true);
        $sword = new Weapon(3, "Sword", 5, 10, DamageType::SLASHING);

        $this->service->equipItem($character, $sword, EquipmentSlot::MAIN_HAND);
        $this->assertNotNull($character->getEquipment()->getItem(EquipmentSlot::MAIN_HAND));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Main hand must be empty to equip a 2-handed weapon.");

        $this->service->equipItem($character, $weapon2h, EquipmentSlot::MAIN_HAND);
    }
}
