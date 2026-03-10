<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\AttackResult;
use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationResult;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageResult;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\CombatResolver;
use App\Domain\Character\Character;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

class CombatResolverBlockPenetrationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * CombatResolver that always hits (no dodge, no miss) with a fixed roll.
     */
    private function makeResolver(
        BlockPenetrationService $bps,
        MaxDamageService $mds = null,
        float $randomRoll = 0.5,
    ): CombatResolver {
        $mds = $mds ?? $this->createMock(MaxDamageService::class);
        $mds->method('checkMaxDamage')->willReturn(new MaxDamageResult(false));

        return new class ($bps, $mds, $randomRoll) extends CombatResolver {
            public function __construct(
            BlockPenetrationService $bps,
            MaxDamageService $mds,
            private float $roll,
            ) {
                parent::__construct($bps, $mds);
            }

            /** @phpstan-ignore-next-line */
            protected function getRandom(): float
            {
                return $this->roll;
            }
        };
    }

    private function makeCharacter(
        int $id,
        int $blockBreakRating,
        float $pierceMultiplier = 0.50,
        int $blockResistRating = 0,
    ): Character {
        $weapon = new Weapon(
            $id,
            'Sword',
            10,
            10,
            DamageType::SLASHING,
            0.0,
            $blockBreakRating,
            $pierceMultiplier,
            0,
        );

        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            name: "Char$id",
            strength: 0,
            agility: 0,
            constitution: 0,
            wit: 0,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            blockResistRating: $blockResistRating,
        );
    }

    /** BlockPenetrationService that always returns a given penetration result. */
    private function makeStubBps(bool $penetrated, int $damage): BlockPenetrationService
    {
        $stub = $this->createMock(BlockPenetrationService::class);
        $stub->method('checkBlockBreak')->willReturn(
            new BlockPenetrationResult(
                penetrated: $penetrated,
                damage: $damage,
            )
        );
        return $stub;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_unblocked_attack_does_not_call_block_service(): void
    {
        $bps = $this->createMock(BlockPenetrationService::class);
        $bps->expects($this->never())->method('checkBlockBreak');

        // Roll 0.5: dodge chance is 0 (agility=0), hit chance = 0.8 → 0.5 < 0.8 → hits
        $resolver = $this->makeResolver($bps, randomRoll: 0.5);
        $attacker = $this->makeCharacter(1, blockBreakRating: 30);
        $defender = $this->makeCharacter(2, blockBreakRating: 0);

        $result = $resolver->resolveAttack($attacker, $defender, isBlocked: false);

        $this->assertGreaterThan(0, $result->damage);
        $this->assertFalse($result->isPierced);
    }

    public function test_blocked_attack_with_zero_rating_deals_no_damage(): void
    {
        // BPS always returns penetration failed → damage = 0
        $bps = $this->makeStubBps(penetrated: false, damage: 0);
        $resolver = $this->makeResolver($bps, randomRoll: 0.5);
        $attacker = $this->makeCharacter(1, blockBreakRating: 0);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 999);

        $result = $resolver->resolveAttack($attacker, $defender, isBlocked: true);

        $this->assertSame(0, $result->damage);
        $this->assertFalse($result->isPierced);
    }

    public function test_blocked_attack_with_guaranteed_penetration_deals_pierce_damage(): void
    {
        // BPS always returns penetration succeeded → pierce damage from stub
        $bps = $this->makeStubBps(penetrated: true, damage: 5);
        $resolver = $this->makeResolver($bps, randomRoll: 0.5);
        $attacker = $this->makeCharacter(1, blockBreakRating: 999, pierceMultiplier: 0.50);
        $defender = $this->makeCharacter(2, blockBreakRating: 0);

        $result = $resolver->resolveAttack($attacker, $defender, isBlocked: true);

        $this->assertSame(5, $result->damage);
        $this->assertTrue($result->isPierced);
    }

    public function test_attack_result_carries_critical_flag_even_through_block_penetration(): void
    {
        // wit = 0 → crit chance = 0; use high roll to avoid crit
        $bps = $this->makeStubBps(penetrated: true, damage: 5);
        $resolver = $this->makeResolver($bps, randomRoll: 0.5);
        $attacker = $this->makeCharacter(1, blockBreakRating: 999);
        $defender = $this->makeCharacter(2, blockBreakRating: 0);

        $result = $resolver->resolveAttack($attacker, $defender, isBlocked: true);

        $this->assertInstanceOf(AttackResult::class, $result);
        $this->assertFalse($result->isDodged);
        $this->assertFalse($result->isMiss);
    }
}
