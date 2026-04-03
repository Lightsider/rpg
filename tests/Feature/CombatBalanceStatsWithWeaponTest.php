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
use App\Services\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use PHPUnit\Framework\TestCase;

/**
 * Combat balance with archetype weapons (no armor/seals).
 *
 * Each build uses its matching archetype weapon:
 *   Tank/Power  → Guardian
 *   Crit        → Executioner
 *   Hybrid/Uni  → Balanced
 */
class CombatBalanceStatsWithWeaponTest extends TestCase
{
    private const int BATTLES_PER_TEST = 100;

    // ── Weapon templates (from ItemSeeder) ──────────────────────────────

    private const STEADFAST_SWORD = [
        'name' => 'Steadfast Sword',
        'minDamage' => 9.0,
        'maxDamage' => 11.0,
        'damageType' => DamageType::SLASHING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 20,
        'pierceMultiplier' => 0.50,
        'maxDamageRating' => 75,
        'flatCritBonus' => 0.0,
        'critChanceBonus' => 0.0,
        'archetype' => WeaponArchetype::STABLE,
    ];

    private const STEADFAST_AXE = [
        'name' => 'Steadfast Axe',
        'minDamage' => 9.0,
        'maxDamage' => 11.0,
        'damageType' => DamageType::CHOPPING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 60,
        'pierceMultiplier' => 0.65,
        'maxDamageRating' => 0,
        'flatCritBonus' => 0.0,
        'critChanceBonus' => 0.0,
        'archetype' => WeaponArchetype::STABLE,
    ];

    private const EXECUTIONER_SWORD = [
        'name' => 'Executioner Sword',
        'minDamage' => 7.0,
        'maxDamage' => 9.0,
        'damageType' => DamageType::SLASHING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 20,
        'pierceMultiplier' => 0.50,
        'maxDamageRating' => 75,
        'flatCritBonus' => 10.0,
        'critChanceBonus' => 7.0,
        'archetype' => WeaponArchetype::CRIT,
    ];

    private const EXECUTIONER_AXE = [
        'name' => 'Executioner Axe',
        'minDamage' => 7.0,
        'maxDamage' => 9.0,
        'damageType' => DamageType::CHOPPING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 60,
        'pierceMultiplier' => 0.65,
        'maxDamageRating' => 0,
        'flatCritBonus' => 10.0,
        'critChanceBonus' => 7.0,
        'archetype' => WeaponArchetype::CRIT,
    ];

    private const VERSATILE_SWORD = [
        'name' => 'Versatile Sword',
        'minDamage' => 8.5,
        'maxDamage' => 10.5,
        'damageType' => DamageType::SLASHING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 20,
        'pierceMultiplier' => 0.50,
        'maxDamageRating' => 75,
        'flatCritBonus' => 4.0,
        'critChanceBonus' => 3.0,
        'archetype' => WeaponArchetype::HYBRID,
    ];

    private const VERSATILE_AXE = [
        'name' => 'Versatile Axe',
        'minDamage' => 8.5,
        'maxDamage' => 10.5,
        'damageType' => DamageType::CHOPPING,
        'accuracyBonus' => 0.0,
        'blockBreakRating' => 60,
        'pierceMultiplier' => 0.65,
        'maxDamageRating' => 0,
        'flatCritBonus' => 4.0,
        'critChanceBonus' => 2.0,
        'archetype' => WeaponArchetype::HYBRID,
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
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver);
    }

    // =====================================================================
    // Weapon factory
    // =====================================================================

    private function createWeapon(int $id, array $tpl): Weapon
    {
        return new Weapon(
            $id,
            $tpl['name'],
            $tpl['minDamage'],
            $tpl['maxDamage'],
            $tpl['damageType'],
            $tpl['accuracyBonus'],
            $tpl['blockBreakRating'],
            $tpl['pierceMultiplier'],
            $tpl['maxDamageRating'],
            $tpl['archetype'],
            requiredStrength: 0,
            requiredWit: 0,
            flatCritBonus: $tpl['flatCritBonus'],
            critChanceBonus: $tpl['critChanceBonus'] ?? 0.0,
        );
    }

    /**
     * Return the archetype weapon matching a build name.
     */
    private function archetypeWeapon(string $build, bool $isSword, int $id): Weapon
    {
        return match ($build) {
            'Stable', 'Tank', 'Universal', 'Dodge' => $this->createWeapon($id, $isSword ? self::STEADFAST_SWORD : self::STEADFAST_AXE),
            'Crit' => $this->createWeapon($id, $isSword ? self::EXECUTIONER_SWORD : self::EXECUTIONER_AXE),
            'Hybrid' => $this->createWeapon($id, $isSword ? self::VERSATILE_SWORD : self::VERSATILE_AXE),
            default => throw new \InvalidArgumentException("Unknown build: {$build}"),
        };
    }

    // =====================================================================
    // Stat definitions (16 point pool)
    // =====================================================================

    private function createStableStats(): array
    {
        return ['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0];
    }

    private function createCritStats(): array
    {
        return ['str' => 4, 'con' => 8, 'dex' => 0, 'wit' => 4];
    }

    private function createHybridStats(): array
    {
        return ['str' => 6, 'con' => 8, 'dex' => 0, 'wit' => 2];
    }

    private function createTankStats(): array
    {
        return ['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0];
    }

    private function createDodgeStats(): array
    {
        return ['str' => 8, 'con' => 4, 'dex' => 4, 'wit' => 0];
    }

    private function createUniversalStats(): array
    {
        return ['str' => 8, 'con' => 6, 'dex' => 2, 'wit' => 0];
    }

    // =====================================================================
    // Character creation
    // =====================================================================

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
            blockResistRating: 0,
        );
    }

    // =====================================================================
    // Combat simulation
    // =====================================================================

    private function simulateBattle(Character $charA, Character $charB): array
    {
        $battle = new Battle(1, 1, [$charA, $charB], new Map(10, 10));

        $rounds = 0;
        $initialHpA = $charA->getCurrentHp();
        $initialHpB = $charB->getCurrentHp();
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

                if ($log->type === BattleLogType::HIT || $log->type === BattleLogType::BLOCK_BREAK) {
                    if ($log->actorId === $aId)
                        $hitsA++;
                    elseif ($log->actorId === $bId)
                        $hitsB++;
                }

                if ($log->type === BattleLogType::CRIT) {
                    if ($log->actorId === $aId)
                        $critsA++;
                    elseif ($log->actorId === $bId)
                        $critsB++;
                }

                if ($log->type === BattleLogType::BLOCK_BREAK) {
                    if ($log->actorId === $aId)
                        $blockBreaksA++;
                    elseif ($log->actorId === $bId)
                        $blockBreaksB++;
                }

                if ($log->type === BattleLogType::MAX_DAMAGE) {
                    if ($log->actorId === $aId)
                        $maxDamagesA++;
                    elseif ($log->actorId === $bId)
                        $maxDamagesB++;
                }

                if ($log->type === BattleLogType::DODGE) {
                    if ($log->actorId === $aId)
                        $dodgesA++;
                    elseif ($log->actorId === $bId)
                        $dodgesB++;
                }

                if ($log->type === BattleLogType::BLOCK) {
                    if ($log->actorId === $aId)
                        $blocksA++;
                    elseif ($log->actorId === $bId)
                        $blocksB++;
                }
            }

            if (!$battle->isFinished()) {
                $battle->startNewRound();
            }
        }

        $damageDealtByA = max(0, $initialHpB - $charB->getCurrentHp());
        $damageDealtByB = max(0, $initialHpA - $charA->getCurrentHp());

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

    private function queueRandomActions(Battle $battle, Character $character): void
    {
        $zones = [
            TargetZone::HEAD,
            TargetZone::TORSO,
            TargetZone::LEGS,
            TargetZone::LEFT_ARM,
            TargetZone::RIGHT_ARM,
        ];

        for ($i = 0; $i < 2; $i++) {
            $target = $zones[array_rand($zones)];
            $battle->queueAction(new TurnAction($character->getId(), ActionType::ATTACK, $target));
            $character->registerAttackUsage();
            $character->spendAP(1);
        }

        $defendZone = $zones[array_rand($zones)];
        $battle->queueAction(new TurnAction($character->getId(), ActionType::DEFEND, $defendZone));
        $character->spendAP(1);

        $character->commit();
    }

    // =====================================================================
    // Aggregation & output
    // =====================================================================

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

    private function printResults(string $title, string $weaponLabel, array $stats): void
    {
        $avgIncomingA = 2 * $stats['avgRounds'];
        $avgIncomingB = 2 * $stats['avgRounds'];
        $avgDodgeRateA = $avgIncomingA > 0 ? ($stats['avgDodgesA'] / $avgIncomingA) * 100 : 0.0;
        $avgDodgeRateB = $avgIncomingB > 0 ? ($stats['avgDodgesB'] / $avgIncomingB) * 100 : 0.0;

        $output = sprintf(
            "\n========================================\n" .
            "  %s [%s]\n" .
            "========================================\n" .
            "A win: %d%%  B win: %d%%  Draw: %d%%\n" .
            "Avg rounds: %.1f\n" .
            "Avg damage   — A: %.1f  B: %.1f\n" .
            "Hits landed  — A: %.1f  B: %.1f\n" .
            "Crits        — A: %.1f  B: %.1f\n" .
            "Block breaks — A: %.1f  B: %.1f\n" .
            "Max dmg procs— A: %.1f  B: %.1f\n" .
            "Dodges (def) — A: %.1f  B: %.1f\n" .
            "Dodge rate   — A: %.1f%% B: %.1f%% (2 attacks/round)\n" .
            "Blocks (def) — A: %.1f  B: %.1f\n" .
            "========================================\n",
            $title,
            $weaponLabel,
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
            $avgDodgeRateA,
            $avgDodgeRateB,
            $stats['avgBlocksA'],
            $stats['avgBlocksB'],
        );

        fwrite(STDOUT, $output);
    }

    // =====================================================================
    // Offensive builds — each uses its archetype weapon
    // =====================================================================

    /**
     * Stable (Steadfast) vs Crit (Executioner) — Sword
     * Stable (Steadfast) vs Hybrid (Versatile)  — Sword
     * Crit  (Executioner) vs Hybrid (Versatile) — Sword
     */
    public function test_offensive_sword(): void
    {
        $stableW = $this->archetypeWeapon('Stable', true, 1);
        $critW = $this->archetypeWeapon('Crit', true, 2);
        $hybridW = $this->archetypeWeapon('Hybrid', true, 3);

        $s = $this->createStableStats();
        $c = $this->createCritStats();
        $h = $this->createHybridStats();

        $r = $this->runSimulation($s, $c, $stableW, $critW, 'Stable', 'Crit');
        $this->printResults('Stable vs Crit', 'Steadfast Sword vs Executioner Sword', $r);

        $r = $this->runSimulation($s, $h, $stableW, $hybridW, 'Stable', 'Hybrid');
        $this->printResults('Stable vs Hybrid', 'Steadfast Sword vs Versatile Sword', $r);

        $r = $this->runSimulation($c, $h, $critW, $hybridW, 'Crit', 'Hybrid');
        $this->printResults('Crit vs Hybrid', 'Executioner Sword vs Versatile Sword', $r);
    }

    /**
     * Same matchups with Axe variants.
     */
    public function test_offensive_axe(): void
    {
        $stableW = $this->archetypeWeapon('Stable', false, 1);
        $critW = $this->archetypeWeapon('Crit', false, 2);
        $hybridW = $this->archetypeWeapon('Hybrid', false, 3);

        $s = $this->createStableStats();
        $c = $this->createCritStats();
        $h = $this->createHybridStats();

        $r = $this->runSimulation($s, $c, $stableW, $critW, 'Stable', 'Crit');
        $this->printResults('Stable vs Crit', 'Steadfast Axe vs Executioner Axe', $r);

        $r = $this->runSimulation($s, $h, $stableW, $hybridW, 'Stable', 'Hybrid');
        $this->printResults('Stable vs Hybrid', 'Steadfast Axe vs Versatile Axe', $r);

        $r = $this->runSimulation($c, $h, $critW, $hybridW, 'Crit', 'Hybrid');
        $this->printResults('Crit vs Hybrid', 'Executioner Axe vs Versatile Axe', $r);
    }

    // =====================================================================
    // Defensive builds — each uses its archetype weapon
    // =====================================================================

    /**
     * Tank (Steadfast Sword) vs Dodge (Versatile Sword)
     * Tank (Steadfast Sword) vs Universal (Versatile Sword)
     * Dodge (Versatile Sword) vs Universal (Versatile Sword)
     */
    public function test_defensive_sword(): void
    {
        $tankW = $this->archetypeWeapon('Tank', true, 1);
        $dodgeW = $this->archetypeWeapon('Dodge', true, 2);
        $uniW = $this->archetypeWeapon('Universal', true, 3);

        $t = $this->createTankStats();
        $d = $this->createDodgeStats();
        $u = $this->createUniversalStats();

        $r = $this->runSimulation($t, $d, $tankW, $dodgeW, 'Tank', 'Dodge');
        $this->printResults('Tank vs Dodge', 'Steadfast Sword vs Steadfast Sword', $r);

        $r = $this->runSimulation($t, $u, $tankW, $uniW, 'Tank', 'Universal');
        $this->printResults('Tank vs Universal', 'Steadfast Sword vs Steadfast Sword', $r);

        $r = $this->runSimulation($d, $u, $dodgeW, $uniW, 'Dodge', 'Universal');
        $this->printResults('Dodge vs Universal', 'Steadfast Sword vs Steadfast Sword', $r);
    }

    /**
     * Same matchups with Axe variants.
     */
    public function test_defensive_axe(): void
    {
        $tankW = $this->archetypeWeapon('Tank', false, 1);
        $dodgeW = $this->archetypeWeapon('Dodge', false, 2);
        $uniW = $this->archetypeWeapon('Universal', false, 3);

        $t = $this->createTankStats();
        $d = $this->createDodgeStats();
        $u = $this->createUniversalStats();

        $r = $this->runSimulation($t, $d, $tankW, $dodgeW, 'Tank', 'Dodge');
        $this->printResults('Tank vs Dodge', 'Steadfast Axe vs Steadfast Axe', $r);

        $r = $this->runSimulation($t, $u, $tankW, $uniW, 'Tank', 'Universal');
        $this->printResults('Tank vs Universal', 'Steadfast Axe vs Steadfast Axe', $r);

        $r = $this->runSimulation($d, $u, $dodgeW, $uniW, 'Dodge', 'Universal');
        $this->printResults('Dodge vs Universal', 'Steadfast Axe vs Steadfast Axe', $r);
    }
}
