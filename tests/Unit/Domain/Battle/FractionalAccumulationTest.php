<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\CombatResolver;
use App\Domain\Battle\MaxDamage\MaxDamageResult;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Domain\Battle\PseudoRandom\PRNGResult;
use App\Domain\Character\Character;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use PHPUnit\Framework\TestCase;

class FractionalAccumulationTest extends TestCase
{
    private function makeCharacter(float $minDamage, float $maxDamage): Character
    {
        $weapon = new Weapon(
            id: 1,
            name: 'Precise Sword',
            minDamage: $minDamage,
            maxDamage: $maxDamage,
            damageType: DamageType::SLASHING
        );

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: 1,
            userId: 1,
            name: 'Attacker',
            strength: 0,
            agility: 0,
            constitution: 0,
            wit: 0,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            damageAccumulator: 0.0
        );
    }

    private function makeDefender(): Character
    {
        return new Character(
            id: 2,
            userId: 2,
            name: 'Defender',
            strength: 0,
            agility: 0,
            constitution: 0,
            wit: 0,
            maxHp: 100,
            currentHp: 100,
            equipment: new Equipment()
        );
    }

    public function test_fractional_damage_is_accumulated_properly(): void
    {
        // 1. Setup resolver with no dodge and no crit
        $bps = $this->createMock(BlockPenetrationService::class);
        $mds = $this->createMock(MaxDamageService::class);
        $mds->method('checkMaxDamage')->willReturn(new MaxDamageResult(false));
        
        $dodgePRNG = $this->createMock(PseudoRandomService::class);
        $dodgePRNG->method('rollWithPRNG')->willReturn(new PRNGResult(success: false, finalChance: 0, randomRoll: 0));
        
        $critPRNG = $this->createMock(PseudoRandomService::class);
        $critPRNG->method('rollWithPRNG')->willReturn(new PRNGResult(success: false, finalChance: 0, randomRoll: 1));

        $resolver = new CombatResolver($bps, $mds, $dodgePRNG, $critPRNG);

        // 2. Setup attacker with 10.4 fixed damage (min=10.4, max=10.4)
        $attacker = $this->makeCharacter(10.4, 10.4);
        $defender = $this->makeDefender();

        // --- Attack 1 ---
        // Expected: 10.4 dealt -> floor(10.4) = 10. Remaining: 0.4.
        $result1 = $resolver->resolveAttack($attacker, $defender, false);
        $this->assertEquals(10, $result1->damage);
        $this->assertEqualsWithDelta(0.4, $attacker->getDamageAccumulator(), 0.0001);

        // --- Attack 2 ---
        // Expected: 10.4 + 0.4 = 10.8. Deals floor(10.8) = 10. Remaining: 0.8
        $result2 = $resolver->resolveAttack($attacker, $defender, false);
        $this->assertEquals(10, $result2->damage);
        $this->assertEqualsWithDelta(0.8, $attacker->getDamageAccumulator(), 0.0001);

        // --- Attack 3 ---
        // Expected: 10.4 + 0.8 = 11.2. Deals floor(11.2) = 11. Remaining: 0.2
        $result3 = $resolver->resolveAttack($attacker, $defender, false);
        $this->assertEquals(11, $result3->damage);
        $this->assertEqualsWithDelta(0.2, $attacker->getDamageAccumulator(), 0.0001);
    }

    public function test_large_accumulation_adds_multiple_points_if_needed(): void
    {
         // 1. Setup resolver
        $bps = $this->createMock(BlockPenetrationService::class);
        $mds = $this->createMock(MaxDamageService::class);
        $mds->method('checkMaxDamage')->willReturn(new MaxDamageResult(false));
        $dodgePRNG = $this->createMock(PseudoRandomService::class);
        $dodgePRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 0));
        $critPRNG = $this->createMock(PseudoRandomService::class);
        $critPRNG->method('rollWithPRNG')->willReturn(new PRNGResult(false, 0, 1));
        $resolver = new CombatResolver($bps, $mds, $dodgePRNG, $critPRNG);

        // 2. Attacker starts with 0.9 accumulator.
        $attacker = $this->makeCharacter(10.2, 10.2);
        $attacker->setDamageAccumulator(0.9);
        $defender = $this->makeDefender();

        // 10.2 + 0.9 = 11.1
        $result = $resolver->resolveAttack($attacker, $defender, false);
        $this->assertEquals(11, $result->damage);
        $this->assertEqualsWithDelta(0.1, $attacker->getDamageAccumulator(), 0.0001);
    }
}
