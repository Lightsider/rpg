<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Equipment;

use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentService;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Item\Item;
use App\Domain\Item\ItemType;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class EquipmentServiceTest extends TestCase
{
    private EquipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('equipment.slots', [
            'main_hand' => ['weapon'],
            'off_hand' => ['shield', 'offhand_weapon'],
            'helmet' => ['helmet'],
            'gloves' => ['gloves'],
            'chest' => ['chest_armor'],
            'legs' => ['leg_armor'],
            'charm_1' => ['charm'],
            'charm_2' => ['charm'],
            'charm_3' => ['charm'],
            'charm_4' => ['charm'],
        ]);

        $this->service = new EquipmentService();
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

    public function test_character_equipment_default_state_is_empty(): void
    {
        $character = $this->createCharacter();

        $equipped = $this->service->getEquippedItems($character);
        $this->assertEmpty($equipped);
    }

    public function test_can_equip_valid_item_in_slot(): void
    {
        $character = $this->createCharacter();
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);

        $this->service->equipItem($character, $weapon, EquipmentSlot::MAIN_HAND);

        $equipped = $this->service->getEquippedItems($character);
        $this->assertCount(1, $equipped);
        $this->assertSame($weapon, $equipped[EquipmentSlot::MAIN_HAND->value]);
    }

    public function test_cannot_equip_invalid_item_in_slot(): void
    {
        $character = $this->createCharacter();
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot equip item type 'weapon' into slot 'helmet'.");

        // Weapon in helmet slot -> BAD
        $this->service->equipItem($character, $weapon, EquipmentSlot::HELMET);
    }

    public function test_can_unequip_item(): void
    {
        $character = $this->createCharacter();
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);

        $this->service->equipItem($character, $weapon, EquipmentSlot::MAIN_HAND);
        $this->assertCount(1, $this->service->getEquippedItems($character));

        $this->service->unequipItem($character, EquipmentSlot::MAIN_HAND);
        $this->assertCount(0, $this->service->getEquippedItems($character));
    }
}



