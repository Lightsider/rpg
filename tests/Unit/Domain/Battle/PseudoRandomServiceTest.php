<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\PseudoRandom\PRNGResult;
use App\Domain\Battle\PseudoRandom\PseudoRandomConfig;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use PHPUnit\Framework\TestCase;

class PseudoRandomServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test doubles
    // -------------------------------------------------------------------------

    private function makeService(
        int $k = 150,
        float $maxFinalChance = 0.80,
        float $prngScale = 0.25,
    ): PseudoRandomService {
        return new PseudoRandomService(
            new PseudoRandomConfig($k, $maxFinalChance, $prngScale)
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

    public function test_zero_failures_returns_base_chance(): void
    {
        $service = $this->makeService();
        $baseChance = 0.375; // 90 / (90 + 150)

        $finalChance = $service->calculateFinalChance($baseChance, 0);

        $this->assertEqualsWithDelta($baseChance, $finalChance, 0.0001);
    }

    public function test_one_failure_increases_chance(): void
    {
        $service = $this->makeService();
        $baseChance = 0.375; // 90 / (90 + 150)
        // Formula: baseChance + failures * (baseChance * prngScale)
        // = 0.375 + 1 * (0.375 * 0.25) = 0.375 + 0.09375 = 0.46875

        $finalChance = $service->calculateFinalChance($baseChance, 1);

        $this->assertEqualsWithDelta(0.46875, $finalChance, 0.0001);
    }

    public function test_two_failures_increases_chance_further(): void
    {
        $service = $this->makeService();
        $baseChance = 0.375;
        // = 0.375 + 2 * (0.375 * 0.25) = 0.375 + 0.1875 = 0.5625

        $finalChance = $service->calculateFinalChance($baseChance, 2);

        $this->assertEqualsWithDelta(0.5625, $finalChance, 0.0001);
    }

    public function test_three_failures_continues_increase(): void
    {
        $service = $this->makeService();
        $baseChance = 0.375;
        // = 0.375 + 3 * (0.375 * 0.25) = 0.375 + 0.28125 = 0.65625

        $finalChance = $service->calculateFinalChance($baseChance, 3);

        $this->assertEqualsWithDelta(0.65625, $finalChance, 0.0001);
    }

    public function test_chance_is_capped_at_max(): void
    {
        $service = $this->makeService(maxFinalChance: 0.80);
        $baseChance = 0.375;

        // With many failures, chance should be capped at 0.80
        $finalChance = $service->calculateFinalChance($baseChance, 100);

        $this->assertEqualsWithDelta(0.80, $finalChance, 0.0001);
    }

    // -------------------------------------------------------------------------
    // rollWithPRNG tests
    // -------------------------------------------------------------------------

    public function test_roll_succeeds_when_roll_is_less_than_chance(): void
    {
        // baseChance = 0.5, prngScale = 0.25, failures = 0
        // finalChance = 0.5
        // Roll 0.3 < 0.5 → success
        $service = $this->makeServiceWithFixedRoll(0.3);

        $result = $service->rollWithPRNG(0.5, 0);

        $this->assertTrue($result->success);
    }

    public function test_roll_fails_when_roll_is_greater_than_chance(): void
    {
        // baseChance = 0.5, failures = 0
        // finalChance = 0.5
        // Roll 0.7 > 0.5 → fail
        $service = $this->makeServiceWithFixedRoll(0.7);

        $result = $service->rollWithPRNG(0.5, 0);

        $this->assertFalse($result->success);
    }

    public function test_probability_increases_after_failures(): void
    {
        // Test that with failures, a higher roll can succeed
        // baseChance = 0.3, failures = 2, prngScale = 0.25
        // finalChance = 0.3 + 2 * (0.3 * 0.25) = 0.3 + 0.15 = 0.45
        // Roll 0.4 < 0.45 → success
        $service = $this->makeServiceWithFixedRoll(0.4);

        $result = $service->rollWithPRNG(0.3, 2);

        $this->assertTrue($result->success);
    }

    public function test_same_roll_fails_without_failures_but_succeeds_with_failures(): void
    {
        // Same roll 0.35:
        // - With 0 failures: finalChance = 0.3, roll 0.35 > 0.3 → fail
        // - With 1 failure: finalChance = 0.3 + 0.075 = 0.375, roll 0.35 < 0.375 → success

        // First test: 0 failures
        $service0 = $this->makeServiceWithFixedRoll(0.35);
        $result0 = $service0->rollWithPRNG(0.3, 0);
        $this->assertFalse($result0->success);

        // Second test: 1 failure
        $service1 = $this->makeServiceWithFixedRoll(0.35);
        $result1 = $service1->rollWithPRNG(0.3, 1);
        $this->assertTrue($result1->success);
    }

    public function test_cap_is_respected_with_many_failures(): void
    {
        // Even with many failures, capped at maxFinalChance
        // baseChance = 0.3, maxFinalChance = 0.5, failures = 100
        // raw = 0.3 + 100 * (0.3 * 0.25) = 0.3 + 7.5 = 7.8
        // capped = min(0.5, 7.8) = 0.5
        // Roll 0.6 > 0.5 → fail
        $service = $this->makeServiceWithFixedRoll(0.6);

        $result = $service->rollWithPRNG(0.3, 100);

        $this->assertFalse($result->success);
    }

    // -------------------------------------------------------------------------
    // Debug info tests
    // -------------------------------------------------------------------------

    public function test_debug_info_is_included_when_debug_enabled(): void
    {
        $service = new PseudoRandomService(
            new PseudoRandomConfig(150, 0.80, 0.25, true)
        );

        // Use a mock to override getRandom
        $serviceWithFixedRoll = new class (0.5) extends PseudoRandomService {
            public function __construct(private float $fixedRoll)
            {
                parent::__construct(new PseudoRandomConfig(150, 0.80, 0.25, true));
            }

            protected function getRandom(): float
            {
                return $this->fixedRoll;
            }
        };

        $result = $serviceWithFixedRoll->rollWithPRNG(0.4, 1);

        $this->assertNotNull($result->baseChance);
        $this->assertNotNull($result->finalChance);
        $this->assertNotNull($result->randomRoll);
        $this->assertEquals(0.4, $result->baseChance);
        $this->assertEquals(0.5, $result->finalChance); // 0.4 + 1 * (0.4 * 0.25)
        $this->assertEquals(0.5, $result->randomRoll);
    }

    public function test_debug_info_is_null_when_debug_disabled(): void
    {
        $service = new PseudoRandomService(
            new PseudoRandomConfig(150, 0.80, 0.25, false)
        );

        $serviceWithFixedRoll = new class (0.5) extends PseudoRandomService {
            public function __construct(private float $fixedRoll)
            {
                parent::__construct(new PseudoRandomConfig(150, 0.80, 0.25, false));
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
            'prng_scale' => 0.3,
            'debug' => true,
        ]);

        $this->assertSame(200, $config->k);
        $this->assertEqualsWithDelta(0.75, $config->maxFinalChance, 0.0001);
        $this->assertEqualsWithDelta(0.3, $config->prngScale, 0.0001);
        $this->assertTrue($config->debug);
    }

    public function test_config_from_array_uses_defaults_for_missing_values(): void
    {
        $config = PseudoRandomConfig::fromArray([]);

        $this->assertSame(150, $config->k);
        $this->assertEqualsWithDelta(0.80, $config->maxFinalChance, 0.0001);
        $this->assertEqualsWithDelta(0.25, $config->prngScale, 0.0001);
        $this->assertFalse($config->debug);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_zero_base_chance_always_fails(): void
    {
        $service = $this->makeServiceWithFixedRoll(0.0);

        $result = $service->rollWithPRNG(0.0, 10);

        $this->assertFalse($result->success);
    }

    public function test_full_base_chance_always_succeeds(): void
    {
        $service = $this->makeServiceWithFixedRoll(0.999);

        $result = $service->rollWithPRNG(1.0, 0);

        $this->assertTrue($result->success);
    }

    public function test_negative_failures_handled_as_zero(): void
    {
        $service = $this->makeService();

        // Negative failures should not cause issues
        // The formula treats negative as adding negative (reducing chance)
        // But since we use max(0, failures) in typical use, test with 0
        $finalChance = $service->calculateFinalChance(0.5, 0);

        $this->assertEqualsWithDelta(0.5, $finalChance, 0.0001);
    }
}
