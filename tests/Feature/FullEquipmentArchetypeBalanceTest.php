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
use App\Services\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Seal\Seal;
use App\Domain\Armor\Shield;
use App\Domain\Weapon\Dagger;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use PHPUnit\Framework\TestCase;

/**
 * Full Equipment Archetype Balance Test
 *
 * Tests combat balance across all offhand + weapon combinations.
 *
 * DEFENSE tests (Stable attack fixed): Tank vs Dodge, Dodge vs Uni, Tank vs Uni
 * ATTACK tests  (Tank defense fixed):  Stable vs Crit, Stable vs Hybrid, Crit vs Hybrid
 *
 * Each matchup runs 8 equipment combos:
 *   Shield vs Dagger (Sword×Sword, Sword×Axe, Axe×Sword, Axe×Axe)
 *   Dagger vs 2H Axe (Sword, Axe)
 *   Shield vs 2H Axe (Sword, Axe)
 *
 * Total: 6 matchups × 8 combos = 48 simulations
 */
class FullEquipmentArchetypeBalanceTest extends TestCase
{
    private const int BATTLES_PER_TEST = 100;

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

    // =========================================================================
    // Stats Builders
    // =========================================================================

    private function getStats(string $archetype): array
    {
        return match ($archetype) {
            'STABLE', 'TANK' => ['str' => 8, 'agi' => 0, 'con' => 8, 'wit' => 0],
            'CRIT' => ['str' => 4, 'agi' => 0, 'con' => 8, 'wit' => 4],
            'DODGE' => ['str' => 8, 'agi' => 4, 'con' => 4, 'wit' => 0],
            'HYBRID' => ['str' => 6, 'agi' => 0, 'con' => 8, 'wit' => 2],
            'UNI' => ['str' => 8, 'agi' => 2, 'con' => 6, 'wit' => 0],
        };
    }

    // =========================================================================
    // Gear Builders
    // =========================================================================

    private function createStableGear(string $base): array
    {
        $isSword = str_contains($base, 'SWORD');
        $is2H = $base === '2H_AXE';
        $isDagger = $base === 'DAGGER';
        $min = 9.0;
        $max = 11.0;
        if ($isDagger) {
            $min = 4.5;
            $max = 5.5;
        } elseif ($is2H) {
            $min = 11.7;
            $max = 14.3;
        }

        $weapon = new Weapon(
            id: rand(1000, 9000),
            name: "Stable $base",
            minDamage: $min,
            maxDamage: $max,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            blockBreakRating: $is2H ? 120 : ($isSword ? 20 : 60),
            pierceMultiplier: $isSword ? 0.5 : ($is2H ? 0.75 : 0.65),
            maxDamageRating: ($isSword || $is2H) ? 75 : 0,
            archetype: WeaponArchetype::STABLE,
            isTwoHanded: $is2H
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Stable Seal",
                minDamage: 0.7,
                maxDamage: 0.8,
                archetype: WeaponArchetype::STABLE,
                requiredStrength: 4
            );
        }
        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createCritGear(string $base): array
    {
        $isSword = str_contains($base, 'SWORD');
        $is2H = $base === '2H_AXE';
        $isDagger = $base === 'DAGGER';
        $min = 7.0;
        $max = 9.0;
        $flat = 10.0;
        if ($isDagger) {
            $min = 3.5;
            $max = 4.5;
            $flat = 5.0;
        } elseif ($is2H) {
            $min = 9.1;
            $max = 11.7;
            $flat = 13.0;
        }

        $weapon = new Weapon(
            id: rand(1000, 9000),
            name: "Crit $base",
            minDamage: $min,
            maxDamage: $max,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            blockBreakRating: $is2H ? 120 : ($isSword ? 20 : 60),
            pierceMultiplier: $isSword ? 0.5 : ($is2H ? 0.75 : 0.65),
            archetype: WeaponArchetype::CRIT,
            flatCritBonus: $flat,
            maxDamageRating: ($isSword || $is2H) ? 75 : 0,
            critChanceBonus: 5.0,
            isTwoHanded: $is2H
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Crit Seal",
                minDamage: 0.5,
                maxDamage: 0.65,
                flatCritBonus: 0.8,
                critChanceBonus: 0.7,
                archetype: WeaponArchetype::CRIT,
                requiredStrength: 4
            );
        }
        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createHybridGear(string $base): array
    {
        $isSword = str_contains($base, 'SWORD');
        $is2H = $base === '2H_AXE';
        $isDagger = $base === 'DAGGER';
        $min = 8.5;
        $max = 10.5;
        $flat = 4.5;
        if ($isDagger) {
            $min = 4.25;
            $max = 5.25;
            $flat = 2.25;
        } elseif ($is2H) {
            $min = 11.05;
            $max = 13.65;
            $flat = 5.5;
        }

        $weapon = new Weapon(
            id: rand(1000, 9000),
            name: "Hybrid $base",
            minDamage: $min,
            maxDamage: $max,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            blockBreakRating: $is2H ? 120 : ($isSword ? 20 : 60),
            pierceMultiplier: $isSword ? 0.5 : ($is2H ? 0.75 : 0.65),
            archetype: WeaponArchetype::HYBRID,
            flatCritBonus: $flat,
            critChanceBonus: 3.0,
            maxDamageRating: ($isSword || $is2H) ? 75 : 0,
            isTwoHanded: $is2H
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Hybrid Seal",
                minDamage: 0.65,
                maxDamage: 0.7,
                flatCritBonus: 0.4,
                critChanceBonus: 0.6,
                archetype: WeaponArchetype::HYBRID,
                requiredStrength: 6
            );
        }
        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createTankDefense(string $offhandBase): array
    {
        $armor = [
            new Armor(rand(100, 900), 'Tank Helm', 6.0, 0.0, ArmorSubtype::HELMET, 0, 0, 0, 8),
            new Armor(rand(100, 900), 'Tank Chest', 6.0, 0.0, ArmorSubtype::BODY, 0, 0, 0, 8),
            new Armor(rand(100, 900), 'Tank Legs', 6.0, 0.0, ArmorSubtype::BOOTS, 0, 0, 0, 8),
            new Armor(rand(100, 900), 'Tank Arms', 6.0, 0.0, ArmorSubtype::GLOVES, 0, 0, 0, 8),
        ];
        $offhand = null;
        if ($offhandBase === 'DAGGER')
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 3.5, 4.5, parryRating: 5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Tank Shield', 40, 0.10, 0, 2.0, 0.0);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    private function createDodgeDefense(string $offhandBase): array
    {
        $armor = [
            new Armor(rand(100, 900), 'Dodge Helm', 0.0, 12.0, ArmorSubtype::HELMET, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Chest', 0.0, 12.0, ArmorSubtype::BODY, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Legs', 0.0, 12.0, ArmorSubtype::BOOTS, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Arms', 0.0, 12.0, ArmorSubtype::GLOVES, 0, 0, 4, 4),
        ];
        $offhand = null;
        if ($offhandBase === 'DAGGER')
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 3.5, 4.5, parryRating: 5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Dodge Shield', 40, 0.10, 0, 0.0, 5.0);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    private function createUniDefense(string $offhandBase): array
    {
        $armor = [
            new Armor(rand(100, 900), 'Uni Helm', 4.0, 2.5, ArmorSubtype::HELMET, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Chest', 4.0, 2.5, ArmorSubtype::BODY, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Legs', 4.0, 2.5, ArmorSubtype::BOOTS, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Arms', 4.0, 2.5, ArmorSubtype::GLOVES, 0, 0, 6, 2),
        ];
        $offhand = null;
        if ($offhandBase === 'DAGGER')
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 3.5, 4.5, parryRating: 5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Uni Shield', 40, 0.10, 0, 1, 1.5);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    // =========================================================================
    // Fighter Builder
    // =========================================================================

    private function createFighter(string $name, int $id, string $archetype, string $weaponBase, string $offhandBase): Character
    {
        $stats = $this->getStats($archetype);

        $attackGear = match ($archetype) {
            'STABLE', 'TANK', 'DODGE', 'UNI' => $this->createStableGear($weaponBase),
            'CRIT' => $this->createCritGear($weaponBase),
            'HYBRID' => $this->createHybridGear($weaponBase),
        };

        $defenseGear = match ($archetype) {
            'TANK', 'STABLE', 'CRIT', 'HYBRID' => $this->createTankDefense($offhandBase),
            'DODGE' => $this->createDodgeDefense($offhandBase),
            'UNI' => $this->createUniDefense($offhandBase),
        };

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $attackGear['weapon']);
        if ($defenseGear['offhand']) {
            $equipment->setItem(EquipmentSlot::OFF_HAND, $defenseGear['offhand']);
        }

        foreach ($attackGear['seals'] as $idx => $seal) {
            $slot = match ($idx) {
                0 => EquipmentSlot::SEAL_1,
                1 => EquipmentSlot::SEAL_2,
                2 => EquipmentSlot::SEAL_3,
                3 => EquipmentSlot::SEAL_4,
            };
            $equipment->setItem($slot, $seal);
        }

        foreach ($defenseGear['armor'] as $idx => $piece) {
            $slot = match ($idx) {
                0 => EquipmentSlot::HELMET,
                1 => EquipmentSlot::CHEST,
                2 => EquipmentSlot::LEGS,
                3 => EquipmentSlot::GLOVES,
            };
            $equipment->setItem($slot, $piece);
        }

        $maxHp = (int) ceil(55 + ($stats['con'] * 8.5));

        $char = new Character(
            id: $id,
            userId: $id,
            name: $name,
            strength: $stats['str'],
            agility: $stats['agi'],
            constitution: $stats['con'],
            wit: $stats['wit'],
            maxHp: $maxHp,
            currentHp: $maxHp,
            equipment: $equipment,
            maxActionPoints: 3,
            currentActionPoints: 3,
            attackPointsUsed: 0,
            x: $id === 1 ? 0 : 1,
            y: 0
        );

        // Apply equipment HP bonuses (e.g. Shield +10% max HP)
        $char->setCurrentHp($char->getMaxHp());
        $char->initializeAdArmor();
        return $char;
    }

    // =========================================================================
    // Core Helpers (matching FullArchetypeBalanceTest)
    // =========================================================================

    private function queueActions(Battle $battle, Character $char): void
    {
        $zones = [TargetZone::HEAD, TargetZone::TORSO, TargetZone::LEGS, TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM];

        // Main-hand attacks
        while ($char->canQueueAttack()) {
            $battle->queueAction(new TurnAction(
                characterId: $char->getId(),
                type: ActionType::ATTACK,
                targetZone: $zones[array_rand($zones)]
            ));
            $char->registerAttackUsage();
            $char->spendAP(1);
        }

        // Off-hand attacks (dagger)
        while ($char->canQueueOffhandAttack()) {
            $battle->queueAction(new TurnAction(
                characterId: $char->getId(),
                type: ActionType::ATTACK_OFFHAND,
                targetZone: $zones[array_rand($zones)]
            ));
            $char->registerOffhandAttackUsage();
            $char->spendAP(1);
        }

        // Defense — all characters block with remaining AP
        // Base: 1 block (from 3 AP - 2 attacks)
        // Shield: 2 blocks (from 4 AP - 2 attacks, shield gives +1 defensive AP)
        while ($char->canQueueDefense()) {
            $battle->queueAction(new TurnAction(
                characterId: $char->getId(),
                type: ActionType::DEFEND,
                targetZone: $zones[array_rand($zones)]
            ));
            $char->spendAP(1);
        }

        $char->commit();
    }

    // =========================================================================
    // Simulation Runner (matching FullArchetypeBalanceTest)
    // =========================================================================

    private function runSimulation(string $archA, string $wA, string $offA, string $archB, string $wB, string $offB): array
    {
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
        $totalParriesA = 0;
        $totalParriesB = 0;

        for ($i = 0; $i < self::BATTLES_PER_TEST; $i++) {
            $charA = $this->createFighter('A', 1, $archA, $wA, $offA);
            $charB = $this->createFighter('B', 2, $archB, $wB, $offB);

            $battle = new Battle($i + 1, 1, [$charA, $charB], new Map(10, 10));
            $deadA = false;
            $deadB = false;

            while (!$battle->isFinished()) {
                $totalRounds++;
                $charA->resetRoundState();
                $charB->resetRoundState();
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

                        if ($log->outcome === 'parry') {
                            if ($log->targetId === $aId) $totalParriesA++;
                            elseif ($log->targetId === $bId) $totalParriesB++;
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
            'avgParriesA' => $totalParriesA / self::BATTLES_PER_TEST,
            'avgParriesB' => $totalParriesB / self::BATTLES_PER_TEST,
        ];
    }

    private function printResults(string $title, string $matchup, array $results): void
    {
        $avgIncomingA = $results['avgHitsB'] + $results['avgDodgesA'] + $results['avgBlocksA'] + $results['avgParriesA'] + $results['avgBlockBreaksB'] ?? 0;
        $avgIncomingB = $results['avgHitsA'] + $results['avgDodgesB'] + $results['avgBlocksB'] + $results['avgParriesB'] + $results['avgBlockBreaksA'] ?? 0;

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
            "Parries(def) — A: %.1f  B: %.1f\n" .
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
            $results['avgParriesA'],
            $results['avgParriesB'],
        );

        fwrite(STDOUT, $output);
    }

    // =========================================================================
    // Equipment Combos
    // =========================================================================

    /**
     * Returns all 8 equipment combos to test for each archetype matchup.
     *
     * @return array<array{string, string, string, string}> [weaponA, offhandA, weaponB, offhandB]
     */
    private function getEquipmentCombos(): array
    {
        return [
            // Shield vs Dagger (4 weapon permutations)
            ['1H_SWORD', 'SHIELD', '1H_SWORD', 'DAGGER'],
            ['1H_SWORD', 'SHIELD', '1H_AXE',   'DAGGER'],
            ['1H_AXE',   'SHIELD', '1H_SWORD', 'DAGGER'],
            ['1H_AXE',   'SHIELD', '1H_AXE',   'DAGGER'],

            // Dagger vs 2H Axe (2 weapon options)
            ['1H_SWORD', 'DAGGER', '2H_AXE', 'NONE'],
            ['1H_AXE',   'DAGGER', '2H_AXE', 'NONE'],

            // Shield vs 2H Axe (2 weapon options)
            ['1H_SWORD', 'SHIELD', '2H_AXE', 'NONE'],
            ['1H_AXE',   'SHIELD', '2H_AXE', 'NONE'],
        ];
    }

    /**
     * Runs all 8 equipment combos for a given archetype matchup.
     */
    private function runFullMatchup(string $section, string $archA, string $archB): void
    {
        foreach ($this->getEquipmentCombos() as [$wA, $offA, $wB, $offB]) {
            $res = $this->runSimulation($archA, $wA, $offA, $archB, $wB, $offB);
            $this->printResults(
                "$section | $archA($wA+$offA) vs $archB($wB+$offB)",
                "$archA vs $archB",
                $res
            );
        }

        $this->assertTrue(true);
    }

    // =========================================================================
    // DEFENSE BALANCE TESTS (Fixed Stable Attack)
    // =========================================================================

    public function test_defense_balance_tank_vs_dodge(): void
    {
        $this->runFullMatchup('DEF', 'TANK', 'DODGE');
    }

    public function test_defense_balance_dodge_vs_uni(): void
    {
        $this->runFullMatchup('DEF', 'DODGE', 'UNI');
    }

    public function test_defense_balance_tank_vs_uni(): void
    {
        $this->runFullMatchup('DEF', 'TANK', 'UNI');
    }

    // =========================================================================
    // ATTACK BALANCE TESTS (Fixed Tank Defense)
    // =========================================================================

    public function test_attack_balance_stable_vs_crit(): void
    {
        $this->runFullMatchup('ATK', 'STABLE', 'CRIT');
    }

    public function test_attack_balance_stable_vs_hybrid(): void
    {
        $this->runFullMatchup('ATK', 'STABLE', 'HYBRID');
    }

    public function test_attack_balance_crit_vs_hybrid(): void
    {
        $this->runFullMatchup('ATK', 'CRIT', 'HYBRID');
    }
}
