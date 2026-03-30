<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\PseudoRandom\PRNGResult;
use App\Domain\Battle\PseudoRandom\PseudoRandomConfig;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use Tests\TestCase;

class PseudoRandomServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test doubles
    // -------------------------------------------------------------------------

    private function makeService(
        int $k = 150,
        float $maxFinalChance = 0.95,
        float $upBonusFactor = 0.1,
        float $downPenaltyFactor = 0.05,
    ): PseudoRandomService {
        return new PseudoRandomService(
            new PseudoRandomConfig($k, $maxFinalChance, $upBonusFactor, $downPenaltyFactor)
        );
    }

    /**
     * Subclass with a fixed random value for deterministic testing.
     */
    private function makeServiceWithFixedRoll(float $roll): PseudoRandomService
    {
        return new class ($roll) extends PseudoRandomService {
            public function __construct(private float $fixedRoll)
            {
                parent::__construct(new PseudoRandomConfig());
            }

            protected function getRandom(): float
            {
                return $this->fixedRoll;
            }
        };
    }

    // -------------------------------------------------------------------------
    // calculateFinalChance tests
    // -------------------------------------------------------------------------

    public function test_zero_streaks_returns_base_chance(): void
    {
        $service = $this->makeService();
        $baseChance = 0.375;

        // Formula: baseChance * (1 + 0*up - 0*down) = baseChance * 1.0
        $finalChance = $service->calculateFinalChance($baseChance, 0, 0);

        $this->assertEqualsWithDelta($baseChance, $finalChance, 0.0001);
    }

    public function test_one_failure_increases_chance_by_ten_percent(): void
    {
        $service = $this->makeService(upBonusFactor: 0.1);
        $baseChance = 0.375; 
        // Formula: 0.375 * (1 + 1 * 0.1) = 0.375 * 1.1 = 0.4125

        $finalChance = $service->calculateFinalChance($baseChance, 1, 0);

        $this->assertEqualsWithDelta(0.4125, $finalChance, 0.0001);
    }

    public function test_success_decreases_chance_by_five_percent(): void
    {
        $service = $this->makeService(downPenaltyFactor: 0.05);
        $baseChance = 0.40;
        // Formula: 0.40 * (1 - 1 * 0.05) = 0.40 * 0.95 = 0.38

        $finalChance = $service->calculateFinalChance($baseChance, 0, 1);

        $this->assertEqualsWithDelta(0.38, $finalChance, 0.0001);
    }

    public function test_two_successes_decreases_chance_further(): void
    {
        $service = $this->makeService(downPenaltyFactor: 0.05);
        $baseChance = 0.40;
        // Formula: 0.40 * (1 - 2 * 0.05) = 0.40 * 0.90 = 0.36

        $finalChance = $service->calculateFinalChance($baseChance, 0, 2);

        $this->assertEqualsWithDelta(0.36, $finalChance, 0.0001);
    }

    public function test_chance_is_capped_at_max(): void
    {
        $service = $this->makeService(maxFinalChance: 0.80);
        $baseChance = 0.40;

        // With many failures, chance should be capped at 0.80
        $finalChance = $service->calculateFinalChance($baseChance, 100, 0);

        $this->assertEqualsWithDelta(0.80, $finalChance, 0.0001);
    }

    public function test_chance_cannot_go_below_zero(): void
    {
        $service = $this->makeService(downPenaltyFactor: 0.5); // -50% per success
        $baseChance = 0.20;

        // With 3 successes, modifier = 1.0 - 1.5 = -0.5
        // Final chance = 0.20 * -0.5 = -0.10. Capped at 0.0
        $finalChance = $service->calculateFinalChance($baseChance, 0, 3);

        $this->assertEqualsWithDelta(0.0, $finalChance, 0.0001);
    }

    // -------------------------------------------------------------------------
    // rollWithPRNG tests
    // -------------------------------------------------------------------------

    public function test_roll_succeeds_when_roll_is_less_than_chance(): void
    {
        $service = $this->makeServiceWithFixedRoll(0.3);

        $result = $service->rollWithPRNG(0.5, 0, 0);

        $this->assertTrue($result->success);
    }

    public function test_roll_fails_when_roll_is_greater_than_chance(): void
    {
        $service = $this->makeServiceWithFixedRoll(0.7);

        $result = $service->rollWithPRNG(0.5, 0, 0);

        $this->assertFalse($result->success);
    }

    public function test_same_roll_fails_without_failures_but_succeeds_with_failures(): void
    {
        // Roll 0.35:
        // - With 0 failures: finalChance = 0.3, roll 0.35 > 0.3 → fail
        // - With 2 failures: finalChance = 0.3 * 1.2 = 0.36, roll 0.35 < 0.36 → success

        // First test: 0 failures
        $service0 = $this->makeServiceWithFixedRoll(0.35);
        $result0 = $service0->rollWithPRNG(0.3, 0);
        $this->assertFalse($result0->success);

        // Second test: 2 failures
        $service1 = $this->makeServiceWithFixedRoll(0.35);
        $result1 = $service1->rollWithPRNG(0.3, 2);
        $this->assertTrue($result1->success);
    }

    // -------------------------------------------------------------------------
    // Debug info tests
    // -------------------------------------------------------------------------

    public function test_debug_info_is_included_when_debug_enabled(): void
    {
        $serviceWithFixedRoll = new class (0.35) extends PseudoRandomService {
            public function __construct(private float $fixedRoll)
            {
                parent::__construct(new PseudoRandomConfig(150, 0.80, 0.1, 0.05, 0.25, true));
            }

            protected function getRandom(): float
            {
                return $this->fixedRoll;
            }
        };

        $result = $serviceWithFixedRoll->rollWithPRNG(0.3, 2); // final: 0.3 * 1.2 = 0.36

        $this->assertNotNull($result->baseChance);
        $this->assertNotNull($result->finalChance);
        $this->assertNotNull($result->randomRoll);
        $this->assertEquals(0.3, $result->baseChance);
        $this->assertEquals(0.36, $result->finalChance);
        $this->assertEquals(0.35, $result->randomRoll);
    }

    public function test_debug_info_is_null_when_debug_disabled(): void
    {
        $serviceWithFixedRoll = new class (0.5) extends PseudoRandomService {
            public function __construct(private float $fixedRoll)
            {
                parent::__construct(new PseudoRandomConfig(150, 0.80, 0.1, 0.05, 0.25, false));
            }

            protected function getRandom(): float
            {
                return $this->fixedRoll;
            }
        };

        $result = $serviceWithFixedRoll->rollWithPRNG(0.4, 1);

        $this->assertNull($result->baseChance);
        $this->assertNull($result->finalChance);
        $this->assertNull($result->randomRoll);
    }

    // -------------------------------------------------------------------------
    // Config tests
    // -------------------------------------------------------------------------

    public function test_config_from_array_creates_correct_config(): void
    {
        $config = PseudoRandomConfig::fromArray([
            'k' => 200,
            'max_final_chance' => 0.75,
            'up_bonus_factor' => 0.2,
            'down_penalty_factor' => 0.1,
            'debug' => true,
        ]);

        $this->assertSame(200, $config->k);
        $this->assertEqualsWithDelta(0.75, $config->maxFinalChance, 0.0001);
        $this->assertEqualsWithDelta(0.2, $config->upBonusFactor, 0.0001);
        $this->assertEqualsWithDelta(0.1, $config->downPenaltyFactor, 0.0001);
        $this->assertTrue($config->debug);
    }

    public function test_config_from_array_uses_defaults_for_missing_values(): void
    {
        $config = PseudoRandomConfig::fromArray([]);

        $this->assertSame(150, $config->k);
        $this->assertEqualsWithDelta(0.95, $config->maxFinalChance, 0.0001);
        $this->assertEqualsWithDelta(0.1, $config->upBonusFactor, 0.0001);
        $this->assertEqualsWithDelta(0.05, $config->downPenaltyFactor, 0.0001);
        $this->assertFalse($config->debug);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_zero_base_chance_always_fails(): void
    {
        $service = $this->makeServiceWithFixedRoll(0.0);

        $result = $service->rollWithPRNG(0.0, 10, 0);

        $this->assertFalse($result->success);
    }

    public function test_high_base_chance_capped_by_max_can_still_fail(): void
    {
        // maxFinalChance = 0.95 (default)
        // baseChance = 1.0
        // finalChance = 0.95
        // Roll 0.99 > 0.95 → fail
        $service = $this->makeServiceWithFixedRoll(0.99);

        $result = $service->rollWithPRNG(1.0, 0, 0);

        $this->assertFalse($result->success);
    }
}
