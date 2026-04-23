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
use App\Domain\Battle\Rewards\BattleRewardsConfig;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

/**
 * Tests balance between different stat builds
 * Maybe it doesn't make sense to test it
 */
class CombatBalanceStatsOnlyTest extends TestCase
{
    private const int BATTLES_PER_TEST = 100;

    // Weapon definitions
    private const EMPTY_WEAPON_TEMPLATE = [
        'minDamage' => 0,
        'maxDamage' => 0,
        'damageType' => DamageType::CRUSH,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 20,
        'pierceMultiplier' => 0.50,
        'maxDamageRating' => 0,
    ];

    private RoundResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $bpsConfig = new BlockPenetrationConfig(120, 0.95, 0.20);
        $bps = new BlockPenetrationService($bpsConfig);

        $mdsConfig = new MaxDamageConfig(300, 0.80, 0.20);
        $mds = new MaxDamageService($mdsConfig);

        $combatResolver = new CombatResolver($bps, $mds);

        $repoMock = $this->createMock(BattleRepositoryInterface::class);
        $movementResolver = $this->createMock(MovementResolver::class);

        $effectivenessConfig = new BattleRewardsConfig(
            armorKoefs: [
                'tank' => 1.0,
                'universal' => 1.16,
                'dodge' => 1.38,
                'non_armor' => 1.0,
            ],
            levelKoef: 1.5
        );

        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver, $effectivenessConfig);
    }

    // =========================================================================
    // Defensive Archetype Definitions
    // =========================================================================

    /**
     * Tank: focuses on constitution for HP
     */
    private function createTankStats(): array
    {
        return [
            'str' => 8,  
            'con' => 8,               
            'dex' => 0,                
            'wit' => 0,                
        ];
    }

    /**
     * Dodge: focuses on evasion
     */
    private function createDodgeStats(): array
    {
        return [
            'str' => 8,  
            'con' => 4,               
            'dex' => 4,               
            'wit' => 0,               
        ];
    }

    /**
     * Universal: balanced approach
     */
    private function createUniversalStats(): array
    {
        return [
            'str' => 8,  
            'con' => 6,              
            'dex' => 2,               
            'wit' => 0,               
        ];
    }

    // =========================================================================
    // Offensive Archetype Definitions
    // =========================================================================

    /**
     * Stable: maximum damage, consistent
     */
    private function createStableStats(): array
    {
        return [
            'str' => 8,              
            'con' => 8,  
            'dex' => 0,               
            'wit' => 0,               
        ];
    }

    /**
     * Crit: focuses on critical hits
     */
    private function createCritStats(): array
    {
        return [
            'str' => 4,  
            'con' => 8,  
            'dex' => 0,               
            'wit' => 4,              
        ];
    }

    /**
     * Hybrid: balanced offense
     */
    private function createHybridStats(): array
    {
        return [
            'str' => 6,               
            'con' => 8,  
            'dex' => 0,               
            'wit' => 2,               
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

        $maxHp = (int) ceil(40 + ($stats['con'] * 8.5));

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

    private function createEmptyWeapon(int $id): Weapon
    {
        return new Weapon(
            $id,
            'Empty',
            self::EMPTY_WEAPON_TEMPLATE['minDamage'],
            self::EMPTY_WEAPON_TEMPLATE['maxDamage'],
            self::EMPTY_WEAPON_TEMPLATE['damageType'],
            self::EMPTY_WEAPON_TEMPLATE['accuracyBonus'],
            self::EMPTY_WEAPON_TEMPLATE['blockBreakRating'],
            self::EMPTY_WEAPON_TEMPLATE['pierceMultiplier'],
            self::EMPTY_WEAPON_TEMPLATE['maxDamageRating']
        );
    }

    // =========================================================================
    // Combat Simulation
    // =========================================================================

    /**
     * Run a battle simulation between two characters.
     */
    private function simulateBattle(Character $charA, Character $charB): array
    {
        $battle = new Battle(1, 1, [$charA, $charB], new Map(10, 10));

        $rounds = 0;
        $damageDealtByA = 0;
        $damageDealtByB = 0;
        $critsA = 0;
        $critsB = 0;
        $blockBreaksA = 0;
        $blockBreaksB = 0;
        $maxDamagesA = 0;
        $maxDamagesB = 0;
        $dodgesA = 0;
        $dodgesB = 0;
        $blocksA = 0;
        $blocksB = 0;
        $hitsA = 0;
        $hitsB = 0;

        while (!$battle->isFinished()) {
            $rounds++;
            $charA->resetRoundState();
            $charB->resetRoundState();

            $this->queueRandomActions($battle, $charA);
            $this->queueRandomActions($battle, $charB);

            $result = $this->resolver->resolve($battle);

            foreach ($result->logs as $log) {
                $aId = $charA->getId();
                $bId = $charB->getId();

                if ($log->type === BattleLogType::ATTACK) {
                    if (in_array($log->outcome, ['hit', 'block_break'], true) && $log->damage !== null) {
                        if ($log->actorId === $aId) {
                            $damageDealtByA += $log->damage;
                            $hitsA++;
                        } elseif ($log->actorId === $bId) {
                            $damageDealtByB += $log->damage;
                            $hitsB++;
                        }
                    }

                    if ($log->isCrit) {
                        if ($log->actorId === $aId) $critsA++;
                        elseif ($log->actorId === $bId) $critsB++;
                    }

                    if ($log->outcome === 'block_break') {
                        if ($log->actorId === $aId) $blockBreaksA++;
                        elseif ($log->actorId === $bId) $blockBreaksB++;
                    }

                    if ($log->isMax) {
                        if ($log->actorId === $aId) $maxDamagesA++;
                        elseif ($log->actorId === $bId) $maxDamagesB++;
                    }

                    if ($log->outcome === 'dodge') {
                        if ($log->targetId === $aId) $dodgesA++;
                        elseif ($log->targetId === $bId) $dodgesB++;
                    }

                    if ($log->outcome === 'block') {
                        if ($log->targetId === $aId) $blocksA++;
                        elseif ($log->targetId === $bId) $blocksB++;
                    }
                }
            }

            if (!$battle->isFinished()) {
                $battle->startNewRound();
            }
        }

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
            'critsA' => $critsA,
            'critsB' => $critsB,
            'blockBreaksA' => $blockBreaksA,
            'blockBreaksB' => $blockBreaksB,
            'maxDamagesA' => $maxDamagesA,
            'maxDamagesB' => $maxDamagesB,
            'dodgesA' => $dodgesA,
            'dodgesB' => $dodgesB,
            'blocksA' => $blocksA,
            'blocksB' => $blocksB,
            'hitsA' => $hitsA,
            'hitsB' => $hitsB,
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
            $battle->queueAction(new TurnAction(
                characterId: $character->getId(),
                type: ActionType::ATTACK,
                targetZone: $target
            ));
            $character->registerAttackUsage();
            $character->spendAP(1);
        }

        // 1 defense
        $defendZone = $zones[array_rand($zones)];
        $battle->queueAction(new TurnAction(
            characterId: $character->getId(),
            type: ActionType::DEFEND,
            targetZone: $defendZone
        ));
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
        $totalCritsA = 0;
        $totalCritsB = 0;
        $totalBlockBreaksA = 0;
        $totalBlockBreaksB = 0;
        $totalMaxDamagesA = 0;
        $totalMaxDamagesB = 0;
        $totalDodgesA = 0;
        $totalDodgesB = 0;
        $totalBlocksA = 0;
        $totalBlocksB = 0;
        $totalHitsA = 0;
        $totalHitsB = 0;

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
            $totalCritsA += $result['critsA'];
            $totalCritsB += $result['critsB'];
            $totalBlockBreaksA += $result['blockBreaksA'];
            $totalBlockBreaksB += $result['blockBreaksB'];
            $totalMaxDamagesA += $result['maxDamagesA'];
            $totalMaxDamagesB += $result['maxDamagesB'];
            $totalDodgesA += $result['dodgesA'];
            $totalDodgesB += $result['dodgesB'];
            $totalBlocksA += $result['blocksA'];
            $totalBlocksB += $result['blocksB'];
            $totalHitsA += $result['hitsA'];
            $totalHitsB += $result['hitsB'];
        }

        return [
            'winRateA' => $winsA / self::BATTLES_PER_TEST,
            'winRateB' => $winsB / self::BATTLES_PER_TEST,
            'drawRate' => $draws / self::BATTLES_PER_TEST,
            'avgRounds' => $totalRounds / self::BATTLES_PER_TEST,
            'avgDamageA' => $totalDamageA / self::BATTLES_PER_TEST,
            'avgDamageB' => $totalDamageB / self::BATTLES_PER_TEST,
            'avgCritsA' => $totalCritsA / self::BATTLES_PER_TEST,
            'avgCritsB' => $totalCritsB / self::BATTLES_PER_TEST,
            'avgBlockBreaksA' => $totalBlockBreaksA / self::BATTLES_PER_TEST,
            'avgBlockBreaksB' => $totalBlockBreaksB / self::BATTLES_PER_TEST,
            'avgMaxDamagesA' => $totalMaxDamagesA / self::BATTLES_PER_TEST,
            'avgMaxDamagesB' => $totalMaxDamagesB / self::BATTLES_PER_TEST,
            'avgDodgesA' => $totalDodgesA / self::BATTLES_PER_TEST,
            'avgDodgesB' => $totalDodgesB / self::BATTLES_PER_TEST,
            'avgBlocksA' => $totalBlocksA / self::BATTLES_PER_TEST,
            'avgBlocksB' => $totalBlocksB / self::BATTLES_PER_TEST,
            'avgHitsA' => $totalHitsA / self::BATTLES_PER_TEST,
            'avgHitsB' => $totalHitsB / self::BATTLES_PER_TEST,
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
            "Hits landed       — A: %.1f  B: %.1f\n" .
            "Crits             — A: %.1f  B: %.1f\n" .
            "Block breaks      — A: %.1f  B: %.1f\n" .
            "Max damage procs  — A: %.1f  B: %.1f\n" .
            "Dodges (as def)   — A: %.1f  B: %.1f\n" .
            "Blocks (as def)   — A: %.1f  B: %.1f\n" .
            "========================================\n",
            $title,
            $weaponType,
            (int) round($stats['winRateA'] * 100),
            (int) round($stats['winRateB'] * 100),
            (int) round($stats['drawRate'] * 100),
            $stats['avgRounds'],
            $stats['avgDamageA'],
            $stats['avgDamageB'],
            $stats['avgHitsA'],
            $stats['avgHitsB'],
            $stats['avgCritsA'],
            $stats['avgCritsB'],
            $stats['avgBlockBreaksA'],
            $stats['avgBlockBreaksB'],
            $stats['avgMaxDamagesA'],
            $stats['avgMaxDamagesB'],
            $stats['avgDodgesA'],
            $stats['avgDodgesB'],
            $stats['avgBlocksA'],
            $stats['avgBlocksB'],
        );

        fwrite(STDOUT, $output);
    }

    // =========================================================================
    // Test: Defensive Build Balance
    // =========================================================================

    /**
     * Test defensive build balance with Sword vs Sword.
     * Defensive builds tested: Tank, Dodge, Universal
     * Both use Stable attack (STR=10, WIT=0)
     */
    public function test_defensive_builds(): void
    {
        $weapon = $this->createEmptyWeapon(1);

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
        $this->printResults('Tank vs Dodge', $results, 'Defensive builds');

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
        $this->printResults('Tank vs Universal', $results, 'Defensive builds');

        // Dodge vs Universal
        $results = $this->runSimulation(
            $dodgeStats,
            $universalStats,
            $weapon,
            $weapon,
            'Dodge',
            'Universal'
        );
        $this->printResults('Dodge vs Universal', $results, 'Defensive builds');
    }

    // =========================================================================
    // Test: Offensive Build Balance
    // =========================================================================

    /**
     * Test offensive build balance with Sword vs Sword.
     * Offensive builds tested: Stable, Crit, Hybrid
     * Both use Tank defense (CON=10, DEX=0)
     */
    public function test_offensive_builds(): void
    {
        $weapon = $this->createEmptyWeapon(1);

        $stableStats = $this->createStableStats();
        $critStats = $this->createCritStats();
        $hybridStats = $this->createHybridStats();

        // Stable vs Crit
        $results = $this->runSimulation(
            $stableStats,
            $critStats,
            $weapon,
            $weapon,
            'Stable',
            'Crit'
        );
        $this->printResults('Stable vs Crit', $results, 'Offensive builds');

        // Stable vs Hybrid
        $results = $this->runSimulation(
            $stableStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Stable',
            'Hybrid'
        );
        $this->printResults('Stable vs Hybrid', $results, 'Offensive builds');

        // Crit vs Hybrid
        $results = $this->runSimulation(
            $critStats,
            $hybridStats,
            $weapon,
            $weapon,
            'Crit',
            'Hybrid'
        );
        $this->printResults('Crit vs Hybrid', $results, 'Offensive builds');
    }
}
