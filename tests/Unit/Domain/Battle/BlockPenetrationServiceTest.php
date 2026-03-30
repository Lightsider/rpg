<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationResult;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Character\Character;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

class BlockPenetrationServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test doubles
    // -------------------------------------------------------------------------

    private function makeService(
        int $k = 120,
        float $maxFinalChance = 0.95,
        float $upBonus = 0.1,
        float $downPenalty = 0.05,
    ): BlockPenetrationService {
        return new BlockPenetrationService(
            new BlockPenetrationConfig($k, $maxFinalChance, $upBonus, $downPenalty)
        );
    }

    private function makeServiceWithFixedRoll(float $roll, int $k = 120): BlockPenetrationService
    {
        return new class ($roll, new BlockPenetrationConfig($k)) extends BlockPenetrationService {
            public function __construct(private float $fixedRoll, BlockPenetrationConfig $config)
            {
                parent::__construct($config);
            }

            protected function getRandom(): float
            {
                return $this->fixedRoll;
            }
        };
    }

    private function makeCharacter(int $id, int $blockBreakRating, int $blockResistRating = 0): Character
    {
        $weapon = new Weapon($id, 'TestWeapon', 10, 10, DamageType::SLASHING, 0.0, $blockBreakRating, 0.50);
        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            userId: $id,
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

    // -------------------------------------------------------------------------
    // calculateEffectiveRating
    // -------------------------------------------------------------------------

    public function test_effective_rating_is_zero_when_ratings_are_equal(): void
    {
        $service = $this->makeService();
        $attacker = $this->makeCharacter(1, blockBreakRating: 60);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 60);

        $this->assertSame(0, $service->calculateEffectiveRating($attacker, $defender));
    }

    public function test_effective_rating_is_clamped_to_zero_when_negative(): void
    {
        $service = $this->makeService();
        $attacker = $this->makeCharacter(1, blockBreakRating: 10);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 60);

        $this->assertSame(0, $service->calculateEffectiveRating($attacker, $defender));
    }

    public function test_effective_rating_is_difference_when_attacker_is_higher(): void
    {
        $service = $this->makeService();
        $attacker = $this->makeCharacter(1, blockBreakRating: 150);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 30);

        $this->assertSame(120, $service->calculateEffectiveRating($attacker, $defender));
    }

    // -------------------------------------------------------------------------
    // checkBlockBreak – multiplier-based PRNG
    // -------------------------------------------------------------------------

    public function test_zero_streak_gives_base_chance(): void
    {
        // effective = 120, K = 120 → base = 0.5
        // Streak 0/0 → final = 0.5
        $service = $this->makeServiceWithFixedRoll(roll: 0.49, k: 120);
        $attacker = $this->makeCharacter(1, blockBreakRating: 150);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 30);

        $result = $service->checkBlockBreak($attacker, $defender, 100);
        $this->assertTrue($result->penetrated);
        
        $failService = $this->makeServiceWithFixedRoll(roll: 0.51, k: 120);
        $resultFail = $failService->checkBlockBreak($attacker, $defender, 100);
        $this->assertFalse($resultFail->penetrated);
    }

    public function test_failure_streak_increases_chance_multiplicatively(): void
    {
        // base = 0.5, fail streak = 2, upBonus = 0.1
        // final = 0.5 * (1 + 2*0.1) = 0.5 * 1.2 = 0.6
        $service = $this->makeServiceWithFixedRoll(roll: 0.99, k: 120); // first fail
        $attacker = $this->makeCharacter(1, blockBreakRating: 150);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 30);
        
        $service->checkBlockBreak($attacker, $defender, 100); // 1st failure
        $service->checkBlockBreak($attacker, $defender, 100); // 2nd failure
        
        $this->assertSame(2, $attacker->getPenetrationFailStreak());
        
        // Next roll with 0.59 should succeed (final = 0.6)
        $serviceHigh = $this->makeServiceWithFixedRoll(roll: 0.59, k: 120);
        $resultSuccess = $serviceHigh->checkBlockBreak($attacker, $defender, 100);
        $this->assertTrue($resultSuccess->penetrated);
        // After success, streak should reset
        $this->assertSame(0, $attacker->getPenetrationFailStreak());
        $this->assertSame(1, $attacker->getPenetrationSuccessStreak());
    }

    public function test_final_chance_is_capped_at_max(): void
    {
        $service = $this->makeServiceWithFixedRoll(roll: 0.99, k: 120);
        $attacker = $this->makeCharacter(1, blockBreakRating: 150);
        $defender = $this->makeCharacter(2, blockBreakRating: 0, blockResistRating: 30); // base 0.5

        // BUILD UP LARGE STREAK
        for ($i = 0; $i < 50; $i++) {
            $service->checkBlockBreak($attacker, $defender, 100);
        }

        // maxFinalChance = 0.95
        $lowRollService = $this->makeServiceWithFixedRoll(roll: 0.94);
        $this->assertTrue($lowRollService->checkBlockBreak($attacker, $defender, 100)->penetrated);
        
        $highRollService = $this->makeServiceWithFixedRoll(roll: 0.96);
        $this->assertFalse($highRollService->checkBlockBreak($attacker, $defender, 100)->penetrated);
    }

    // -------------------------------------------------------------------------
    // applyPierceDamage
    // -------------------------------------------------------------------------

    public function test_apply_pierce_damage_with_sword_and_axe(): void
    {
        $service = $this->makeService();
        $sword = new Weapon(1, 'Sword', 10, 10, DamageType::SLASHING, 0.0, 30, 0.50);
        $axe = new Weapon(2, 'Axe', 8, 14, DamageType::CHOPPING, 0.0, 60, 0.65);

        $this->assertSame(50, $service->applyPierceDamage(100, $sword));
        $this->assertSame(65, $service->applyPierceDamage(100, $axe));
    }
}
