<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Character;

use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
{
    private function createCharacter(int $ap = 3): Character
    {
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);
        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: 1,
            name: "Hero",
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            maxActionPoints: $ap,
            currentActionPoints: $ap
        );
    }

    public function test_cannot_spend_more_ap_than_available(): void
    {
        $character = $this->createCharacter(3);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not enough Action Points.');
        $character->spendAP(4);
    }

    public function test_cannot_spend_negative_ap(): void
    {
        $character = $this->createCharacter(3);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot spend negative AP.');
        $character->spendAP(-1);
    }

    public function test_cannot_queue_more_than_max_attacks(): void
    {
        $character = $this->createCharacter(3);

        $character->registerAttackUsage();
        $character->registerAttackUsage();

        $this->assertFalse($character->canQueueAttack());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Maximum attacks per round reached.');
        $character->registerAttackUsage();
    }

    public function test_cannot_spend_ap_after_commitment(): void
    {
        $character = $this->createCharacter(3);
        $character->commit();

        $this->assertTrue($character->isCommitted());
        $this->assertFalse($character->canSpendAP(1));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot spend AP after commitment.');
        $character->spendAP(1);
    }

    public function test_cannot_register_attack_after_commitment(): void
    {
        $character = $this->createCharacter(3);
        $character->commit();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot register attack after commitment.');
        $character->registerAttackUsage();
    }

    public function test_reset_round_state_clears_commitment(): void
    {
        $character = $this->createCharacter(3);
        $character->spendAP(1);
        $character->registerAttackUsage();
        $character->commit();

        $character->resetRoundState();

        $this->assertEquals(3, $character->getCurrentActionPoints());
        $this->assertFalse($character->isCommitted());
        $this->assertTrue($character->canQueueAttack());
    }
}
