<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\CombatResolver;
use App\Domain\Battle\MaxDamage\MaxDamageResult;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Domain\Battle\PseudoRandom\PRNGResult;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

class ArmorSystemTest extends TestCase
{
    private function makeCharacter(float $ad = 0.0, float $dodge = 0.0): Character
    {
        $equipment = new Equipment();
        if ($ad > 0 || $dodge > 0) {
            $helmet = new Armor(10, 'Test Helmet', $ad, $dodge, ArmorSubtype::HELMET);
            $equipment->setItem(EquipmentSlot::HELMET, $helmet);
        }

        $char = new Character(
            id: 1,
            userId: 1,
            name: 'Defender',
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            adArmorHead: $ad
        );

        return $char;
    }

    private function makeAttacker(float $damage): Character
    {
        $weapon = new Weapon(1, 'Fixed Weapon', $damage, $damage, DamageType::SLASHING);
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: 2,
            userId: 2,
            name: 'Attacker',
            strength: 0,
            agility: 0,
            constitution: 0,
            wit: 0,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment
        );
    }

    public function test_armor_reduces_damage_by_fifty_percent_to_head(): void
    {
        $bps = $this->createMock(BlockPenetrationService::class);
        $mds = $this->createMock(MaxDamageService::class);
        $mds->method('checkMaxDamage')->willReturn(new MaxDamageResult(false));
        $dodgePRNG = $this->createMock(PseudoRandomService::class);
        $dodgePRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 0));
        $critPRNG = $this->createMock(PseudoRandomService::class);
        $critPRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 1));

        // Forced Head Hit
        $resolver = new class ($bps, $mds, $dodgePRNG, $critPRNG) extends CombatResolver {
            protected function selectHitZone(): string { return 'head'; }
        };

        $defender = $this->makeCharacter(ad: 12.0);
        $attacker = $this->makeAttacker(damage: 10.0);

        // 10 dmg -> 5 HP, 5 Head AD
        $result = $resolver->resolveAttack($attacker, $defender, false);

        $this->assertEquals(5, $result->damage);
        $this->assertEquals(7.0, $defender->getAdArmorForZone('head'));
    }

    public function test_armor_overflow_to_hp(): void
    {
        $bps = $this->createMock(BlockPenetrationService::class);
        $mds = $this->createMock(MaxDamageService::class);
        $mds->method('checkMaxDamage')->willReturn(new MaxDamageResult(false));
        $dodgePRNG = $this->createMock(PseudoRandomService::class);
        $dodgePRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 0));
        $critPRNG = $this->createMock(PseudoRandomService::class);
        $critPRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 1));

        // Forced Head Hit
        $resolver = new class ($bps, $mds, $dodgePRNG, $critPRNG) extends CombatResolver {
            protected function selectHitZone(): string { return 'head'; }
        };

        $defender = $this->makeCharacter(ad: 2.0);
        $attacker = $this->makeAttacker(damage: 10.0);

        // 10 dmg -> 5 HP baseline + (5 AD side - 2 current AD) overflow = 8 HP
        $result = $resolver->resolveAttack($attacker, $defender, false);

        $this->assertEquals(8, $result->damage);
        $this->assertEquals(0.0, $defender->getAdArmorForZone('head'));
    }
}
