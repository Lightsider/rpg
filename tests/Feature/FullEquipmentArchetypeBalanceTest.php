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
 * Compares Shield, Dagger, and 2H Axe builds in a 18-test matrix across archetypes.
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
    // Archetype Factory
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

    private function createFighter(string $name, int $id, string $archetype, string $weaponBase, string $offhandBase): Character
    {
        $stats = $this->getStats($archetype);

        // 1. Get Weapon & Seals (Attacker profile)
        $attackGear = match ($archetype) {
            'STABLE', 'TANK', 'DODGE', 'UNI' => $this->createStableGear($weaponBase),
            'CRIT' => $this->createCritGear($weaponBase),
            'HYBRID' => $this->createHybridGear($weaponBase),
        };

        // 2. Get Armor & Offhand (Defender profile)
        $defenseGear = match ($archetype) {
            'TANK', 'STABLE','CRIT', 'HYBRID' => $this->createTankDefense($offhandBase),
            'DODGE'  => $this->createDodgeDefense($offhandBase),
            'UNI'  => $this->createUniDefense($offhandBase),
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

        $maxHp = (int) ceil(40 + ($stats['con'] * 8.5));

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

        $char->initializeAdArmor();
        return $char;
    }

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
            $sMin = 0.7;
            $sMax = 0.8;
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Stable Seal",
                minDamage: $sMin,
                maxDamage: $sMax,
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
            $sMin = 0.5;
            $sMax = 0.65;
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Crit Seal",
                minDamage: $sMin,
                maxDamage: $sMax,
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
        $flat = 4.0;
        if ($isDagger) {
            $min = 4.25;
            $max = 5.25;
            $flat = 2.0;
        } elseif ($is2H) {
            $min = 11.05;
            $max = 13.65;
            $flat = 5.2;
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
            $sMin = 0.6;
            $sMax = 0.75;
            $seals[] = new Seal(
                id: rand(10000, 90000),
                name: "Hybrid Seal",
                minDamage: $sMin,
                maxDamage: $sMax,
                flatCritBonus: 0.3,
                critChanceBonus: 0.5,
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
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 4.5, 5.5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Tank Shield', 40, 0.10, 0, 2.0, 0.0);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    private function createDodgeDefense(string $offhandBase): array
    {
        $armor = [
            new Armor(rand(100, 900), 'Dodge Helm', 0.0, 3.0, ArmorSubtype::HELMET, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Chest', 0.0, 3.0, ArmorSubtype::BODY, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Legs', 0.0, 3.0, ArmorSubtype::BOOTS, 0, 0, 4, 4),
            new Armor(rand(100, 900), 'Dodge Arms', 0.0, 3.0, ArmorSubtype::GLOVES, 0, 0, 4, 4),
        ];
        $offhand = null;
        if ($offhandBase === 'DAGGER')
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 4.5, 5.5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Dodge Shield', 40, 0.10, 0, 0.0, 1.5);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    private function createUniDefense(string $offhandBase): array
    {
        $armor = [
            new Armor(rand(100, 900), 'Uni Helm', 4.0, 1.0, ArmorSubtype::HELMET, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Chest', 4.0, 1.0, ArmorSubtype::BODY, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Legs', 4.0, 1.0, ArmorSubtype::BOOTS, 0, 0, 6, 2),
            new Armor(rand(100, 900), 'Uni Arms', 4.0, 1.0, ArmorSubtype::GLOVES, 0, 0, 6, 2),
        ];
        $offhand = null;
        if ($offhandBase === 'DAGGER')
            $offhand = new Dagger(rand(1000, 9000), 'Dagger', 4.5, 5.5);
        elseif ($offhandBase === 'SHIELD')
            $offhand = new Shield(rand(1000, 9000), 'Uni Shield', 40, 0.10, 0, 1, 0.5);

        return ['armor' => $armor, 'offhand' => $offhand];
    }

    // =========================================================================
    // Simulation Runner
    // =========================================================================

    private function runSimulation(string $title, string $archA, string $wA, string $offA, string $archB, string $wB, string $offB): void
    {
        $winsA = 0;
        $winsB = 0;
        $draws = 0;
        $totalRounds = 0;
        $totalAttacksA = 0;
        $totalAttacksB = 0;
        $parriesA = 0;
        $parriesB = 0;
        $blocksA = 0;
        $blocksB = 0;

        for ($i = 0; $i < self::BATTLES_PER_TEST; $i++) {
            $charA = $this->createFighter('A', 1, $archA, $wA, $offA);
            $charB = $this->createFighter('B', 2, $archB, $wB, $offB);

            $battle = new Battle($i + 1, 1, [$charA, $charB], new Map(10, 10));
            $deadA = false;
            $deadB = false;

            while (!$battle->isFinished()) {
                $charA->resetRoundState();
                $charB->resetRoundState();
                foreach ([$charA, $charB] as $char) {
                    while ($char->canQueueAttack()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK, TargetZone::TORSO));
                        $char->registerAttackUsage();
                        $char->spendAP(1);
                        if ($char->getId() === 1)
                            $totalAttacksA++;
                        else
                            $totalAttacksB++;
                    }
                    while ($char->canQueueOffhandAttack()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK_OFFHAND, TargetZone::TORSO));
                        $char->registerOffhandAttackUsage();
                        $char->spendAP(1);
                        if ($char->getId() === 1)
                            $totalAttacksA++;
                        else
                            $totalAttacksB++;
                    }
                    while ($char->canQueueDefense()) {
                        $offhand = $char->getEquipment()->getItem(\App\Domain\Equipment\EquipmentSlot::OFF_HAND);
                        if ($offhand instanceof Shield) {
                            $battle->queueAction(new TurnAction($char->getId(), ActionType::DEFEND, TargetZone::TORSO));
                        }
                        $char->spendAP(1);
                    }
                    $char->commit();
                }
                $result = $this->resolver->resolve($battle);
                foreach ($result->logs as $log) {
                    if ($log->type === BattleLogType::ATTACK) {
                        if ($log->outcome === 'parry') {
                            if ($log->targetId === 1)
                                $parriesA++;
                            else
                                $parriesB++;
                        } elseif ($log->outcome === 'block') {
                            if ($log->targetId === 1)
                                $blocksA++;
                            else
                                $blocksB++;
                        }
                    }
                    if ($log->type === BattleLogType::DEATH) {
                        if ($log->actorId === 1)
                            $deadA = true;
                        else
                            $deadB = true;
                    }
                }
                if (!$battle->isFinished())
                    $battle->startNewRound();
            }
            if ($deadA && !$deadB)
                $winsB++;
            elseif ($deadB && !$deadA)
                $winsA++;
            else
                $draws++;
            $totalRounds += $battle->getRoundNumber();
        }

        $output = sprintf(
            "\n[%s] %s (%s+%s) vs %s (%s+%s)\n" .
            "WINS: A:%d, B:%d, Draws:%d | AvgRounds:%.2f\n" .
            "DEF: A(B:%d, P:%d) | B(B:%d, P:%d)\n",
            $title,
            $archA,
            $wA,
            $offA,
            $archB,
            $wB,
            $offB,
            $winsA,
            $winsB,
            $draws,
            $totalRounds / self::BATTLES_PER_TEST,
            $blocksA,
            $parriesA,
            $blocksB,
            $parriesB
        );
        fwrite(STDOUT, $output);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Matrix Generator
    // =========================================================================

    private function runMatchupMatrix(string $wA, string $offA, string $wB, string $offB): void
    {
        $this->runSimulation('STABLE-TANK', 'STABLE', $wA, $offA, 'TANK', $wB, $offB);
        $this->runSimulation('CRIT-DODGE', 'CRIT', $wA, $offA, 'DODGE', $wB, $offB);
        $this->runSimulation('HYBRID-UNI', 'HYBRID', $wA, $offA, 'UNI', $wB, $offB);
    }

    // =========================================================================
    // Test Methods
    // =========================================================================

    public function test_Matrix_2hAxe_vs_SwordShield()
    {
        $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_SWORD', 'SHIELD');
    }
    public function test_Matrix_2hAxe_vs_AxeShield()
    {
        $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_AXE', 'SHIELD');
    }
    public function test_Matrix_2hAxe_vs_SwordDagger()
    {
        $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_SWORD', 'DAGGER');
    }
    public function test_Matrix_2hAxe_vs_AxeDagger()
    {
        $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_AXE', 'DAGGER');
    }
    public function test_Matrix_AxeDagger_vs_AxeShield()
    {
        $this->runMatchupMatrix('1H_AXE', 'DAGGER', '1H_AXE', 'SHIELD');
    }
    public function test_Matrix_SwordDagger_vs_SwordShield()
    {
        $this->runMatchupMatrix('1H_SWORD', 'DAGGER', '1H_SWORD', 'SHIELD');
    }
}
