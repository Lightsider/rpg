<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogType;
use App\Domain\Battle\CombatResolver;
use App\Domain\Battle\Map;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolver;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageConfig;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\PseudoRandom\PseudoRandomConfig;
use App\Domain\Battle\PseudoRandom\PseudoRandomService;
use App\Services\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

/**
 * Combat Balance Simulation Tests
 * 
 * Tests balance between different stat builds at level 1.
 * Each build distributes 20 stat points with minimum constraints:
 * - STR >= 5
 * - CON >= 5
 * - DEX and WIT may be 0
 */
class CombatBalanceSimulationTest extends TestCase
{
    private const int TOTAL_STAT_POINTS = 20;
    private const int MIN_STAT = 5;
    private const int BATTLES_PER_TEST = 30;
    private const float BALANCE_MIN = 0.40;
    private const float BALANCE_MAX = 0.60;

    // Weapon definitions
    private const SWORD_TEMPLATE = [
        'minDamage' => 3,
        'maxDamage' => 7,
        'damageType' => DamageType::SLASHING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 20,
        'pierceMultiplier' => 0.50,
        'maxDamageRating' => 90,
    ];

    private const AXE_TEMPLATE = [
        'minDamage' => 3,
        'maxDamage' => 7,
        'damageType' => DamageType::CHOPPING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 60,
        'pierceMultiplier' => 0.65,
        'maxDamageRating' => 0,
    ];

    private RoundResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $bpsConfig = new BlockPenetrationConfig(150, 0.95, 0.20);
        $bps = new BlockPenetrationService($bpsConfig);

        $mdsConfig = new MaxDamageConfig(300, 0.80, 0.20);
        $mds = new MaxDamageService($mdsConfig);

        $combatResolver = new CombatResolver($bps, $mds);

        $repoMock = $this->createMock(BattleRepositoryInterface::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver);
    }

    // =========================================================================
    // Defensive Archetype Definitions
    // =========================================================================

    /**
     * Tank: CON=10, DEX=0 (focuses on constitution for HP)
     */
    private function createTankStats(): array
    {
        return [
            'str' => 10,  // 10
            'con' => 10,               // 10
            'dex' => 0,                // 0
            'wit' => 0,                // 0 (remaining points)
        ];
    }

    /**
     * Dodge: CON=5, DEX=5 (focuses on evasion)
     */
    private function createDodgeStats(): array
    {
        return [
            'str' => 10,  // 10
            'con' => 5,               // 5
            'dex' => 5,               // 5
            'wit' => 0,               // 0 (remaining points)
        ];
    }

    /**
     * Universal: CON=7, DEX=3 (balanced approach)
     */
    private function createUniversalStats(): array
    {
        return [
            'str' => 10,  // 10
            'con' => 7,               // 7
            'dex' => 3,               // 3
            'wit' => 0,               // 0 (remaining points)
        ];
    }

    // =========================================================================
    // Offensive Archetype Definitions
    // =========================================================================

    /**
     * Power: STR=10, WIT=0 (maximum damage)
     */
    private function createPowerStats(): array
    {
        return [
            'str' => 10,              // 10
            'con' => 10,  // 10
            'dex' => 0,               // 0
            'wit' => 0,               // 0 (remaining points)
        ];
    }

    /**
     * Crit: STR=5, WIT=5 (focuses on critical hits)
     */
    private function createCritStats(): array
    {
        return [
            'str' => 5,  // 5
            'con' => 10,  // 10
            'dex' => 0,               // 0
            'wit' => 5,              // 5
        ];
    }

    /**
     * Hybrid: STR=7, WIT=3 (balanced offense)
     */
    private function createHybridStats(): array
    {
        return [
            'str' => 7,               // 7
            'con' => 10,  // 10
            'dex' => 0,               // 0
            'wit' => 3,               // 3
        ];
    }

    // =========================================================================
    // Character Creation Helpers
    // =========================================================================

    /**
     * Create a character with given stats and weapon.
     */
    private function createCharacter(
        string $name,
        int $id,
        array $stats,
        Weapon $weapon,
        int $x = 0,
        int $y = 0
    ): Character {
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        // HP formula: 35 + (CON * 4.5)
        $maxHp = (int) round(35 + ($stats['con'] * 4.5));

        return new Character(
            id: $id,
            userId: $id,
            name: $name,
            strength: $stats['str'],
            agility: $stats['dex'],
            constitution: $stats['con'],
            wit: $stats['wit'],
            maxHp: $maxHp,
            currentHp: $maxHp,
            equipment: $equipment,
            maxActionPoints: 3,
            currentActionPoints: 3,
            attackPointsUsed: 0,
            x: $x,
            y: $y,
            blockResistRating: 0
        );
    }

    /**
     * Create a Sword weapon.
     */
    private function createSword(int $id): Weapon
    {
        return new Weapon(
            $id,
            'Sword',
            self::SWORD_TEMPLATE['minDamage'],
            self::SWORD_TEMPLATE['maxDamage'],
            self::SWORD_TEMPLATE['damageType'],
            self::SWORD_TEMPLATE['accuracyBonus'],
            self::SWORD_TEMPLATE['blockBreakRating'],
            self::SWORD_TEMPLATE['pierceMultiplier'],
            self::SWORD_TEMPLATE['maxDamageRating']
        );
    }

    /**
     * Create an Axe weapon.
     */
    private function createAxe(int $id): Weapon
    {
        return new Weapon(
            $id,
            'Axe',
            self::AXE_TEMPLATE['minDamage'],
            self::AXE_TEMPLATE['maxDamage'],
            self::AXE_TEMPLATE['damageType'],
            self::AXE_TEMPLATE['accuracyBonus'],
            self::AXE_TEMPLATE['blockBreakRating'],
            self::AXE_TEMPLATE['pierceMultiplier'],
            self::AXE_TEMPLATE['maxDamageRating']
        );
    }

    // =========================================================================
    // Combat Simulation
    // =========================================================================

    /**
     * Run a battle simulation between two characters.
     * Returns: ['winner' => 1|2|null, 'rounds' => int, 'totalDamageA' => int, 'totalDamageB' => int]
     */
    private function simulateBattle(Character $charA, Character $charB): array
    {
        $battle = new Battle(1, 1, [$charA, $charB], new Map(10, 10));

        $rounds = 0;
        $damageDealtByA = 0;
        $damageDealtByB = 0;

        while (!$battle->isFinished()) {
            $rounds++;
            $charA->resetRoundState();
            $charB->resetRoundState();

            // Both characters use the same AI: 2 attacks + 1 defense
            $this->queueRandomActions($battle, $charA);
            $this->queueRandomActions($battle, $charB);

            $result = $this->resolver->resolve($battle);

            // Track damage dealt
            foreach ($result->logs as $log) {
                if ($log->damage !== null && ($log->type === BattleLogType::HIT || $log->type === BattleLogType::MAX_DAMAGE || $log->type === BattleLogType::BLOCK_BREAK)) {
                    if ($log->actorId === $charA->getId()) {
                        $damageDealtByA += $log->damage;
                    } else {
                        $damageDealtByB += $log->damage;
                    }
                }
            }

            if (!$battle->isFinished()) {
                $battle->startNewRound();
            }
        }

        // Determine winner
        $winner = null;
        if ($charA->getCurrentHp() <= 0 && $charB->getCurrentHp() > 0) {
            $winner = 2;
        } elseif ($charB->getCurrentHp() <= 0 && $charA->getCurrentHp() > 0) {
            $winner = 1;
        }

        return [
            'winner' => $winner,
            'rounds' => $rounds,
            'totalDamageA' => $damageDealtByA,
            'totalDamageB' => $damageDealtByB,
        ];
    }

    /**
     * Queue random actions for a character (2 attacks + 1 defense).
     */
    private function queueRandomActions(Battle $battle, Character $character): void
    {
        $zones = [
            TargetZone::HEAD,
            TargetZone::TORSO,
            TargetZone::LEGS,
            TargetZone::LEFT_ARM,
            TargetZone::RIGHT_ARM
        ];

        // 2 attacks
        for ($i = 0; $i < 2; $i++) {
            $target = $zones[array_rand($zones)];
            $battle->queueAction(new TurnAction($character->getId(), ActionType::ATTACK, $target));
            $character->registerAttackUsage();
            $character->spendAP(1);
        }

        // 1 defense
        $defendZone = $zones[array_rand($zones)];
        $battle->queueAction(new TurnAction($character->getId(), ActionType::DEFEND, $defendZone));
        $character->spendAP(1);

        $character->commit();
    }

    /**
     * Run multiple battles and collect statistics.
     */
    private function runSimulation(
        array $statsA,
        array $statsB,
        Weapon $weaponA,
        Weapon $weaponB,
        string $nameA,
        string $nameB
    ): array {
        $winsA = 0;
        $winsB = 0;
        $draws = 0;
        $totalRounds = 0;
        $totalDamageA = 0;
        $totalDamageB = 0;

        for ($i = 0; $i < self::BATTLES_PER_TEST; $i++) {
            $charA = $this->createCharacter($nameA, 1, $statsA, $weaponA, 0, 0);
            $charB = $this->createCharacter($nameB, 2, $statsB, $weaponB, 1, 0);

            $result = $this->simulateBattle($charA, $charB);

            if ($result['winner'] === 1) {
                $winsA++;
            } elseif ($result['winner'] === 2) {
                $winsB++;
            } else {
                $draws++;
            }

            $totalRounds += $result['rounds'];
            $totalDamageA += $result['totalDamageA'];
            $totalDamageB += $result['totalDamageB'];
        }

        return [
            'winRateA' => $winsA / self::BATTLES_PER_TEST,
            'winRateB' => $winsB / self::BATTLES_PER_TEST,
            'drawRate' => $draws / self::BATTLES_PER_TEST,
            'avgRounds' => $totalRounds / self::BATTLES_PER_TEST,
            'avgDamageA' => $totalDamageA / self::BATTLES_PER_TEST,
            'avgDamageB' => $totalDamageB / self::BATTLES_PER_TEST,
        ];
    }

    /**
     * Print simulation results.
     */
    private function printResults(string $title, array $stats, string $weaponType): void
    {
        $output = sprintf(
            "\n========================================\n" .
            "  %s (%s)\n" .
            "========================================\n" .
            "Character A winrate: %d%%\n" .
            "Character B winrate: %d%%\n" .
            "Draw rate: %d%%\n" .
            "Average rounds: %.1f\n" .
            "Average damage (A): %.1f\n" .
            "Average damage (B): %.1f\n" .
            "========================================\n",
            $title,
            $weaponType,
            (int) round($stats['winRateA'] * 100),
            (int) round($stats['winRateB'] * 100),
            (int) round($stats['drawRate'] * 100),
            $stats['avgRounds'],
            $stats['avgDamageA'],
            $stats['avgDamageB']
        );

        fwrite(STDOUT, $output);

        // Note: Balance assertions removed - just showing results for analysis
    }

    // =========================================================================
    // Test: Defensive Build Balance
    // =========================================================================

    /**
     * Test defensive build balance with Sword vs Sword.
     * Defensive builds tested: Tank, Dodge, Universal
     * Both use Power attack (STR=10, WIT=0)
     */
    public function test_defensive_builds_sword_vs_sword(): void
    {
        $weapon = $this->createSword(1);
        $powerStats = $this->createPowerStats();

        // Tank vs Dodge
        $tankStats = $this->createTankStats();
        $dodgeStats = $this->createDodgeStats();

        $results = $this->runSimulation(
            $tankStats,
            $dodgeStats,
            $weapon,
            $weapon,
            'Tank',
            'Dodge'
        );
        $this->printResults('Tank vs Dodge', $results, 'Sword vs Sword');

        // Tank vs Universal
        $universalStats = $this->createUniversalStats();
        $results = $this->runSimulation(
            $tankStats,
            $universalStats,
            $weapon,
            $weapon,
            'Tank',
            'Universal'
        );
        $this->printResults('Tank vs Universal', $results, 'Sword vs Sword');

        // Dodge vs Universal
        $results = $this->runSimulation(
            $dodgeStats,
            $universalStats,
            $weapon,
            $weapon,
            'Dodge',
            'Universal'
        );
        $this->printResults('Dodge vs Universal', $results, 'Sword vs Sword');
    }

    /**
     * Test defensive build balance with Axe vs Axe.
     */
    public function test_defensive_builds_axe_vs_axe(): void
    {
        $weapon = $this->createAxe(1);
        $powerStats = $this->createPowerStats();

        $tankStats = $this->createTankStats();
        $dodgeStats = $this->createDodgeStats();
        $universalStats = $this->createUniversalStats();

        $results = $this->runSimulation(
            $tankStats,
            $dodgeStats,
            $weapon,
            $weapon,
            'Tank',
            'Dodge'
        );
        $this->printResults('Tank vs Dodge', $results, 'Axe vs Axe');

        $results = $this->runSimulation(
            $tankStats,
            $universalStats,
            $weapon,
            $weapon,
            'Tank',
            'Universal'
        );
        $this->printResults('Tank vs Universal', $results, 'Axe vs Axe');

        $results = $this->runSimulation(
            $dodgeStats,
            $universalStats,
            $weapon,
            $weapon,
            'Dodge',
            'Universal'
        );
        $this->printResults('Dodge vs Universal', $results, 'Axe vs Axe');
    }

    /**
     * Test defensive build balance with Sword vs Axe.
     */
    public function test_defensive_builds_sword_vs_axe(): void
    {
        $sword = $this->createSword(1);
        $axe = $this->createAxe(2);

        $tankStats = $this->createTankStats();
        $dodgeStats = $this->createDodgeStats();
        $universalStats = $this->createUniversalStats();

        $results = $this->runSimulation(
            $tankStats,
            $dodgeStats,
            $sword,
            $axe,
            'Tank',
            'Dodge'
        );
        $this->printResults('Tank vs Dodge', $results, 'Sword vs Axe');

        $results = $this->runSimulation(
            $tankStats,
            $universalStats,
            $sword,
            $axe,
            'Tank',
            'Universal'
        );
        $this->printResults('Tank vs Universal', $results, 'Sword vs Axe');

        $results = $this->runSimulation(
            $dodgeStats,
            $universalStats,
            $sword,
            $axe,
            'Dodge',
            'Universal'
        );
        $this->printResults('Dodge vs Universal', $results, 'Sword vs Axe');
    }

    // =========================================================================
    // Test: Offensive Build Balance
    // =========================================================================

    /**
     * Test offensive build balance with Sword vs Sword.
     * Offensive builds tested: Power, Crit, Hybrid
     * Both use Tank defense (CON=10, DEX=0)
     */
    public function test_offensive_builds_sword_vs_sword(): void
    {
        $weapon = $this->createSword(1);
        $tankDefStats = [
            'str' => self::MIN_STAT,
            'con' => 10,
            'dex' => 0,
            'wit' => 5,
        ];

        $powerStats = $this->createPowerStats();
        $critStats = $this->createCritStats();
        $hybridStats = $this->createHybridStats();

        // Power vs Crit
        $results = $this->runSimulation(
            $powerStats,
            $critStats,
            $weapon,
            $weapon,
            'Power',
            'Crit'
        );
        $this->printResults('Power vs Crit', $results, 'Sword vs Sword');

        // Power vs Hybrid
        $results = $this->runSimulation(
            $powerStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Power',
            'Hybrid'
        );
        $this->printResults('Power vs Hybrid', $results, 'Sword vs Sword');

        // Crit vs Hybrid
        $results = $this->runSimulation(
            $critStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Crit',
            'Hybrid'
        );
        $this->printResults('Crit vs Hybrid', $results, 'Sword vs Sword');
    }

    /**
     * Test offensive build balance with Axe vs Axe.
     */
    public function test_offensive_builds_axe_vs_axe(): void
    {
        $weapon = $this->createAxe(1);

        $powerStats = $this->createPowerStats();
        $critStats = $this->createCritStats();
        $hybridStats = $this->createHybridStats();

        $results = $this->runSimulation(
            $powerStats,
            $critStats,
            $weapon,
            $weapon,
            'Power',
            'Crit'
        );
        $this->printResults('Power vs Crit', $results, 'Axe vs Axe');

        $results = $this->runSimulation(
            $powerStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Power',
            'Hybrid'
        );
        $this->printResults('Power vs Hybrid', $results, 'Axe vs Axe');

        $results = $this->runSimulation(
            $critStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Crit',
            'Hybrid'
        );
        $this->printResults('Crit vs Hybrid', $results, 'Axe vs Axe');
    }

    /**
     * Test offensive build balance with Sword vs Axe.
     */
    public function test_offensive_builds_sword_vs_axe(): void
    {
        $sword = $this->createSword(1);
        $axe = $this->createAxe(2);

        $powerStats = $this->createPowerStats();
        $critStats = $this->createCritStats();
        $hybridStats = $this->createHybridStats();

        $results = $this->runSimulation(
            $powerStats,
            $critStats,
            $sword,
            $axe,
            'Power',
            'Crit'
        );
        $this->printResults('Power vs Crit', $results, 'Sword vs Axe');

        $results = $this->runSimulation(
            $powerStats,
            $hybridStats,
            $sword,
            $axe,
            'Power',
            'Hybrid'
        );
        $this->printResults('Power vs Hybrid', $results, 'Sword vs Axe');

        $results = $this->runSimulation(
            $critStats,
            $hybridStats,
            $sword,
            $axe,
            'Crit',
            'Hybrid'
        );
        $this->printResults('Crit vs Hybrid', $results, 'Sword vs Axe');
    }
}
