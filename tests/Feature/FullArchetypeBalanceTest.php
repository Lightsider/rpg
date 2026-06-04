<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
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
use App\Application\Battle\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Seal\Seal;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use App\Domain\Battle\Rewards\BattleRewardsConfig;
use PHPUnit\Framework\TestCase;

/**
 * Full Archetype Balance Tests
 *
 * Simulates battles between characters with full gear sets (Weapon + 4 Seals + 4 Armor).
 * Tests balance across Tank, Crit, and Universal archetypes.
 */
class FullArchetypeBalanceTest extends TestCase
{
    private const int BATTLES_PER_TEST = 100; // 30 for speed, Increase to 100+ for precision

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
    // Stats Builders
    // =========================================================================

    private function getStableStats(): array { return ['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0]; }
    private function getCritStats(): array { return ['str' => 4, 'con' => 8, 'dex' => 0, 'wit' => 4]; }
    private function getHybridStats(): array { return ['str' => 6, 'con' => 8, 'dex' => 0, 'wit' => 2]; }
    private function getTankStats(): array { return ['str' => 8, 'con' => 8, 'dex' => 0, 'wit' => 0]; }
    private function getDodgeStats(): array { return ['str' => 8, 'con' => 4, 'dex' => 4, 'wit' => 0]; }
    private function getUniversalStats(): array { return ['str' => 8, 'con' => 6, 'dex' => 2, 'wit' => 0]; }

    // =========================================================================
    // Gear Builders (Matching ItemSeeder)
    // =========================================================================

    private function createStableGear(bool $isSword): array
    {
        $weapon = new Weapon(
            id: $isSword ? 1 : 2,
            name: $isSword ? 'Steadfast Sword' : 'Steadfast Axe',
            minDamage: 9.0,
            maxDamage: 11.0,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: $isSword ? 20 : 60,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $isSword ? 75 : 0,
            archetype: WeaponArchetype::STABLE,
            requiredStrength: 8
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                7,
                'Steadfast Seal',
                0.6750,
                0.8250,
                0.0,
                0.0,
                WeaponArchetype::STABLE,
                8,
                0
            );
        }

        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createCritGear(bool $isSword): array
    {
        $weapon = new Weapon(
            id: $isSword ? 3 : 4,
            name: $isSword ? 'Executioner Sword' : 'Executioner Axe',
            minDamage: 7.0,
            maxDamage: 9.0,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: $isSword ? 20 : 60,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $isSword ? 75 : 0,
            flatCritBonus: 10.0,
            critChanceBonus: 5.0,
            archetype: WeaponArchetype::CRIT,
            requiredStrength: 4,
            requiredWit: 4
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                8,
                'Executioner Seal',
                0.5,
                0.65,
                0.80,
                0.7,
                WeaponArchetype::CRIT,
                4,
                4
            );
        }

        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createHybridGear(bool $isSword): array
    {
        $weapon = new Weapon(
            id: $isSword ? 5 : 6,
            name: $isSword ? 'Versatile Sword' : 'Versatile Axe',
            minDamage: 9.0,
            maxDamage: 11.0,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: $isSword ? 20 : 60,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $isSword ? 75 : 0,
            flatCritBonus: 4.5,
            critChanceBonus: 3.0,
            archetype: WeaponArchetype::HYBRID,
            requiredStrength: 6,
            requiredWit: 2
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                9,
                'Versatile Seal',
                0.7,
                0.85,
                0.4,
                0.6,
                WeaponArchetype::HYBRID,
                6,
                2
            );
        }

        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createTankArmor(): array
    {
        return [
            new Armor(10, 'Guardian Helmet', 6.0, 0.0, ArmorSubtype::HELMET, 0, 0, 0, 8),
            new Armor(11, 'Guardian Chestplate', 6.0, 0.0, ArmorSubtype::BODY, 0, 0, 0, 8),
            new Armor(12, 'Guardian Boots', 6.0, 0.0, ArmorSubtype::BOOTS, 0, 0, 0, 8),
            new Armor(13, 'Guardian Gauntlets', 6.0, 0.0, ArmorSubtype::GLOVES, 0, 0, 0, 8),
        ];
    }

    private function createDodgeArmor(): array
    {
        return [
            new Armor(14, 'Shadow Helmet', 0.0, 12.0, ArmorSubtype::HELMET, 0, 0, 4, 4),
            new Armor(15, 'Shadow Chestplate', 0.0, 12.0, ArmorSubtype::BODY, 0, 0, 4, 4),
            new Armor(16, 'Shadow Boots', 0.0, 12.0, ArmorSubtype::BOOTS, 0, 0, 4, 4),
            new Armor(17, 'Shadow Gauntlets', 0.0, 12.0, ArmorSubtype::GLOVES, 0, 0, 4, 4),
        ];
    }

    private function createUniArmor(): array
    {
        return [
            new Armor(18, 'Balanced Helmet', 4.0, 2.5, ArmorSubtype::HELMET, 0, 0, 2, 6),
            new Armor(19, 'Balanced Chestplate', 4.0, 2.5, ArmorSubtype::BODY, 0, 0, 2, 6),
            new Armor(20, 'Balanced Boots', 4.0, 2.5, ArmorSubtype::BOOTS, 0, 0, 2, 6),
            new Armor(21, 'Balanced Gauntlets', 4.0, 2.5, ArmorSubtype::GLOVES, 0, 0, 2, 6),
        ];
    }

    // =========================================================================
    // Core Helpers
    // =========================================================================

    private function createFighter(string $name, int $id, array $stats, array $weaponGear, array $armorPieces): Character
    {
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weaponGear['weapon']);
        $equipment->setItem(EquipmentSlot::SEAL_1, $weaponGear['seals'][0]);
        $equipment->setItem(EquipmentSlot::SEAL_2, $weaponGear['seals'][1]);
        $equipment->setItem(EquipmentSlot::SEAL_3, $weaponGear['seals'][2]);
        $equipment->setItem(EquipmentSlot::SEAL_4, $weaponGear['seals'][3]);

        $equipment->setItem(EquipmentSlot::HELMET, $armorPieces[0]);
        $equipment->setItem(EquipmentSlot::CHEST, $armorPieces[1]);
        $equipment->setItem(EquipmentSlot::LEGS, $armorPieces[2]);
        $equipment->setItem(EquipmentSlot::GLOVES, $armorPieces[3]);

        $maxHp = (int) ceil(55 + ($stats['con'] * 8.5));

        $char = new Character(
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
            x: $id === 1 ? 0 : 1, // Adjacent on X
            y: 0
        );

        $char->initializeAdArmor();
        return $char;
    }

    private function runSimulation(callable $factoryA, callable $factoryB, string $nameA, string $nameB): array
    {
        $winsA = 0; $winsB = 0; $draws = 0;
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
            $charA = $factoryA(1);
            $charB = $factoryB(2);
            $battle = new Battle($i, 1, [$charA, $charB], new Map(10, 10));

            $deadA = false; $deadB = false;

            while (!$battle->isFinished()) {
                $totalRounds++;
                $charA->resetRoundState(); $charB->resetRoundState();
                $this->queueActions($battle, $charA);
                $this->queueActions($battle, $charB);
                
                $result = $this->resolver->resolve($battle);
                foreach ($result->logs as $log) {
                    $aId = $charA->getId();
                    $bId = $charB->getId();

                    if ($log->type === BattleLogType::ATTACK) {
                        // Track damage from logs (HP is restored after battle ends)
                        if ($log->damage !== null && $log->damage > 0) {
                            if ($log->actorId === $aId) $totalDamageA += $log->damage;
                            elseif ($log->actorId === $bId) $totalDamageB += $log->damage;
                        }

                        if (in_array($log->outcome, ['hit', 'block_break'], true)) {
                            if ($log->actorId === $aId) $totalHitsA++;
                            elseif ($log->actorId === $bId) $totalHitsB++;
                        }

                        if ($log->isCrit) {
                            if ($log->actorId === $aId) $totalCritsA++;
                            elseif ($log->actorId === $bId) $totalCritsB++;
                        }

                        if ($log->outcome === 'block_break') {
                            if ($log->actorId === $aId) $totalBlockBreaksA++;
                            elseif ($log->actorId === $bId) $totalBlockBreaksB++;
                        }

                        if ($log->isMax) {
                            if ($log->actorId === $aId) $totalMaxDamagesA++;
                            elseif ($log->actorId === $bId) $totalMaxDamagesB++;
                        }

                        if ($log->outcome === 'dodge') {
                            if ($log->targetId === $aId) $totalDodgesA++;
                            elseif ($log->targetId === $bId) $totalDodgesB++;
                        }

                        if ($log->outcome === 'block') {
                            if ($log->targetId === $aId) $totalBlocksA++;
                            elseif ($log->targetId === $bId) $totalBlocksB++;
                        }
                    }

                    if ($log->type === BattleLogType::DEATH) {
                        if ($log->actorId === 1) $deadA = true;
                        if ($log->actorId === 2) $deadB = true;
                    }
                }

                if (!$battle->isFinished()) $battle->startNewRound();
            }

            if ($deadB && !$deadA) $winsA++;
            elseif ($deadA && !$deadB) $winsB++;
            else $draws++;
        }

        return [
            'winRateA' => ($winsA / self::BATTLES_PER_TEST),
            'winRateB' => ($winsB / self::BATTLES_PER_TEST),
            'drawRate' => ($draws / self::BATTLES_PER_TEST),
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

    private function queueActions(Battle $battle, Character $char): void
    {
        $zones = [TargetZone::HEAD, TargetZone::TORSO, TargetZone::LEGS, TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM];
        // 2 random attacks
        for ($i = 0; $i < 2; $i++) {
            $battle->queueAction(new TurnAction(
                characterId: $char->getId(),
                type: ActionType::ATTACK,
                targetZone: $zones[array_rand($zones)]
            ));
            $char->registerAttackUsage(); $char->spendAP(1);
        }
        // 1 random defense
        $battle->queueAction(new TurnAction(
            characterId: $char->getId(),
            type: ActionType::DEFEND,
            targetZone: $zones[array_rand($zones)]
        ));
        $char->spendAP(1);
        $char->commit();
    }

    private function printResults(string $title, string $matchup, array $results): void
    {
        $avgIncomingA = $results['avgHitsB'] + $results['avgDodgesA'] + $results['avgBlocksA'] + $results['avgBlockBreaksB'] ?? 0;
        $avgIncomingB = $results['avgHitsA'] + $results['avgDodgesB'] + $results['avgBlocksB'] + $results['avgBlockBreaksA'] ?? 0;
        $avgDodgeRateA = $avgIncomingA > 0 ? ($results['avgDodgesA'] / $avgIncomingA) * 100 : 0.0;
        $avgDodgeRateB = $avgIncomingB > 0 ? ($results['avgDodgesB'] / $avgIncomingB) * 100 : 0.0;

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
            $matchup,
            (int) round($results['winRateA'] * 100),
            (int) round($results['winRateB'] * 100),
            (int) round($results['drawRate'] * 100),
            $results['avgRounds'],
            $results['avgDamageA'],
            $results['avgDamageB'],
            $results['avgHitsA'],
            $results['avgHitsB'],
            $results['avgCritsA'],
            $results['avgCritsB'],
            $results['avgBlockBreaksA'],
            $results['avgBlockBreaksB'],
            $results['avgMaxDamagesA'],
            $results['avgMaxDamagesB'],
            $results['avgDodgesA'],
            $results['avgDodgesB'],
            $avgDodgeRateA,
            $avgDodgeRateB,
            $results['avgBlocksA'],
            $results['avgBlocksB'],
        );

        fwrite(STDOUT, $output);
    }

    // =========================================================================
    // ATTACK BALANCE TESTS (Fixed Defense)
    // =========================================================================

    public function test_attack_balance_vs_tank_defense(): void
    {
        $this->runAttackFocusTests('Vs Tank Def', $this->createTankArmor());
        $this->assertTrue(true);
    }

    public function test_attack_balance_vs_dodge_defense(): void
    {
        $this->runAttackFocusTests('Vs Dodge Def', $this->createDodgeArmor());
        $this->assertTrue(true);
    }

    public function test_attack_balance_vs_universal_defense(): void
    {
        $this->runAttackFocusTests('Vs Uni Def', $this->createUniArmor());
        $this->assertTrue(true);
    }

    private function runAttackFocusTests(string $title, array $fixedArmor): void
    {
        $weapons = ['Sword' => true, 'Axe' => false];

        foreach ($weapons as $wName => $isSword) {
            // Stable vs Crit
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('StableAtk', $id, $this->getStableStats(), $this->createStableGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('CritAtk', $id, $this->getCritStats(), $this->createCritGear($isSword), $fixedArmor),
                'Stable', 'Crit'
            );
            $this->printResults($title, "Stable vs Crit ($wName)", $res);

            // Stable vs Hybrid
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('StableAtk', $id, $this->getStableStats(), $this->createStableGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('HybridAtk', $id, $this->getHybridStats(), $this->createHybridGear($isSword), $fixedArmor),
                'Stable', 'Hybrid'
            );
            $this->printResults($title, "Stable vs Hybrid ($wName)", $res);

            // Crit vs Hybrid
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('CritAtk', $id, $this->getCritStats(), $this->createCritGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('HybridAtk', $id, $this->getHybridStats(), $this->createHybridGear($isSword), $fixedArmor),
                'Crit', 'Hybrid'
            );
            $this->printResults($title, "Crit vs Hybrid ($wName)", $res);
        }
    }

    // =========================================================================
    // DEFENSE BALANCE TESTS (Fixed Attack)
    // =========================================================================

    public function test_defense_balance_vs_tank_attack(): void
    {
        $this->runDefenseFocusTests('Vs Stable Atk', fn($sword) => $this->createStableGear($sword), $this->getStableStats());
        $this->assertTrue(true);
    }

    public function test_defense_balance_vs_crit_attack(): void
    {
        $this->runDefenseFocusTests('Vs Crit Atk', fn($sword) => $this->createCritGear($sword), $this->getCritStats());
        $this->assertTrue(true);
    }

    public function test_defense_balance_vs_universal_attack(): void
    {
        $this->runDefenseFocusTests('Vs Hybrid Atk', fn($sword) => $this->createHybridGear($sword), $this->getHybridStats());
        $this->assertTrue(true);
    }

    private function runDefenseFocusTests(string $title, callable $atkGearFactory, array $atkStats): void
    {
        $weapons = ['Sword' => true, 'Axe' => false];

        foreach ($weapons as $wName => $isSword) {
            $gear = $atkGearFactory($isSword);

            // Tank vs Dodge
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('TankDef', $id, $this->getTankStats(), $gear, $this->createTankArmor()),
                fn($id) => $this->createFighter('DodgeDef', $id, $this->getDodgeStats(), $gear, $this->createDodgeArmor()),
                'Tank', 'Dodge'
            );
            $this->printResults($title, "Tank vs Dodge ($wName)", $res);

            // Tank vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('TankDef', $id, $this->getTankStats(), $gear, $this->createTankArmor()),
                fn($id) => $this->createFighter('UniDef', $id, $this->getUniversalStats(), $gear, $this->createUniArmor()),
                'Tank', 'Uni'
            );
            $this->printResults($title, "Tank vs Uni ($wName)", $res);

            // Dodge vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('DodgeDef', $id, $this->getDodgeStats(), $gear, $this->createDodgeArmor()),
                fn($id) => $this->createFighter('UniDef', $id, $this->getUniversalStats(), $gear, $this->createUniArmor()),
                'Dodge', 'Uni'
            );
            $this->printResults($title, "Dodge vs Uni ($wName)", $res);
        }
    }
}
