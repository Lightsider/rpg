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

        // 1. Create Weapon
        $weapon = $this->buildWeapon($archetype, $weaponBase);
        
        // 2. Create Off-hand
        $offhand = $this->buildOffhand($archetype, $offhandBase);

        // 3. Create Seals
        $seals = $this->buildSeals($archetype, $weaponBase);

        // 4. Create Armor
        $armor = $this->buildArmor($archetype);

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        if ($offhand) {
            $equipment->setItem($offhand instanceof Shield ? EquipmentSlot::OFF_HAND : EquipmentSlot::OFF_HAND, $offhand);
        }

        foreach ($seals as $idx => $seal) {
            $slot = match($idx) {
                0 => EquipmentSlot::SEAL_1,
                1 => EquipmentSlot::SEAL_2,
                2 => EquipmentSlot::SEAL_3,
                3 => EquipmentSlot::SEAL_4,
            };
            $equipment->setItem($slot, $seal);
        }

        foreach ($armor as $idx => $piece) {
            $slot = match($idx) {
                0 => EquipmentSlot::HELMET,
                1 => EquipmentSlot::CHEST,
                2 => EquipmentSlot::LEGS,
                3 => EquipmentSlot::GLOVES,
            };
            $equipment->setItem($slot, $piece);
        }

        $maxHp = (int) ceil(40 + ($stats['con'] * 8.5));
        
        $char = new Character(
            id: $id, userId: $id, name: $name,
            strength: $stats['str'], agility: $stats['agi'], constitution: $stats['con'], wit: $stats['wit'],
            maxHp: $maxHp, currentHp: $maxHp,
            equipment: $equipment,
            maxActionPoints: 3, currentActionPoints: 3, attackPointsUsed: 0,
            x: $id === 1 ? 0 : 1, y: 0
        );

        $char->initializeAdArmor();
        return $char;
    }

    private function buildWeapon(string $archetype, string $base): Weapon
    {
        $isSword = str_contains($base, 'SWORD');
        $is2H = $base === '2H_AXE';
        $isDagger = $base === 'DAGGER';
        $damageType = $isSword ? DamageType::SLASHING : DamageType::CHOPPING;
        
        $breakBase = $isSword ? 20 : 60;
        if ($is2H) $breakBase = 120;

        // Concrete values for each base
        $minDmg = 9.0; $maxDmg = 11.0; 
        if ($isDagger) { $minDmg = 4.5; $maxDmg = 5.5; }
        elseif ($is2H) { $minDmg = 11.7; $maxDmg = 14.3; }

        $flatCrit = 0.0;
        $critChance = 0.0;
        $maxDmgRating = 0;

        if (str_contains($archetype, 'CRIT')) {
            if ($isDagger) {
                $minDmg = 3.5; $maxDmg = 4.5; $flatCrit = 5.0;
            } elseif ($is2H) {
                $minDmg = 9.1; $maxDmg = 11.7; $flatCrit = 13.0;
            } else {
                $minDmg = 7.0; $maxDmg = 9.0; $flatCrit = 10.0;
            }
            $critChance = 5.0;
        } elseif (str_contains($archetype, 'HYBRID')) {
            if ($isDagger) {
                $minDmg = 4.25; $maxDmg = 5.25; $flatCrit = 2.0;
            } elseif ($is2H) {
                $minDmg = 11.05; $maxDmg = 13.65; $flatCrit = 5.2;
            } else {
                $minDmg = 8.5; $maxDmg = 10.5; $flatCrit = 4.0;
            }
            $critChance = 3.0;
        } elseif (str_contains($archetype, 'STABLE') || $archetype === 'TANK') {
            $maxDmgRating = 75;
        }

        return new Weapon(
            id: rand(1000, 9000),
            name: "Weapon $base $archetype",
            minDamage: $minDmg,
            maxDamage: $maxDmg,
            damageType: $damageType,
            accuracyBonus: 0.0,
            blockBreakRating: $breakBase,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $maxDmgRating,
            flatCritBonus: $flatCrit,
            critChanceBonus: $critChance,
            archetype: WeaponArchetype::STABLE,
            isTwoHanded: $is2H
        );
    }

    private function buildOffhand(string $archetype, string $base): ?\App\Domain\Item\Item
    {
        if ($base === 'NONE') return null;
        if ($base === 'DAGGER') {
            return new Dagger(rand(1000, 9000), 'Dagger', 4.5, 5.5);
        }
        
        // Shield logic
        $block = 40;
        $adArmor = 0.0;
        $dodge = 0.0;

        if ($archetype === 'TANK') {
            $adArmor = 15.0; // 15% armor across all zones
        } elseif ($archetype === 'DODGE') {
            $dodge = 15.0; // 15% dodge chance
        } elseif ($archetype === 'UNI') {
            $adArmor = 7.5;
            $dodge = 7.5;
        }

        return new Shield(rand(1000, 9000), 'Shield', (int) $block, 0.10, 0, $adArmor, $dodge);
    }

    private function buildSeals(string $archetype, string $base): array
    {
        $isDagger = $base === 'DAGGER';
        $is2H = $base === '2H_AXE';
        $seals = [];

        for ($i = 0; $i < 4; $i++) {
            $minOff = 0.7; $maxOff = 0.8; 
            if ($isDagger) { $minOff = 0.35; $maxOff = 0.4; }
            elseif ($is2H) { $minOff = 0.91; $maxOff = 1.04; }

            $flatCrit = 0.0;
            $critChance = 0.0;

            if (str_contains($archetype, 'CRIT')) {
                if ($isDagger) {
                    $minOff = 0.25; $maxOff = 0.325; $flatCrit = 0.4;
                } elseif ($is2H) {
                    $minOff = 0.65; $maxOff = 0.845; $flatCrit = 1.04;
                } else {
                    $minOff = 0.5; $maxOff = 0.65; $flatCrit = 0.8;
                }
                $critChance = 0.7;
            }

            $seals[] = new Seal(
                rand(10000, 90000), "Seal $archetype",
                $minOff, $maxOff, $flatCrit, $critChance,
                WeaponArchetype::STABLE, 4, 0
            );
        }
        return $seals;
    }

    private function buildArmor(string $archetype): array
    {
        $prot = 4.0; $dodge = 1.0;
        if ($archetype === 'TANK') { $prot = 6.0; $dodge = 0.0; }
        if ($archetype === 'DODGE') { $prot = 0.0; $dodge = 3.0; }

        return [
            new Armor(rand(100, 900), 'Head', $prot, $dodge, ArmorSubtype::HELMET, 0, 0, 0, 4),
            new Armor(rand(100, 900), 'Body', $prot, $dodge, ArmorSubtype::BODY, 0, 0, 0, 4),
            new Armor(rand(100, 900), 'Legs', $prot, $dodge, ArmorSubtype::BOOTS, 0, 0, 0, 4),
            new Armor(rand(100, 900), 'Arms', $prot, $dodge, ArmorSubtype::GLOVES, 0, 0, 0, 4),
        ];
    }

    // =========================================================================
    // Simulation Runner
    // =========================================================================

    private function runSimulation(string $title, string $archA, string $wA, string $offA, string $archB, string $wB, string $offB): void
    {
        $winsA = 0; $winsB = 0; $draws = 0;
        $totalRounds = 0;
        $totalAttacksA = 0; $totalAttacksB = 0;
        $parriesA = 0; $parriesB = 0;
        $blocksA = 0; $blocksB = 0;

        for ($i = 0; $i < self::BATTLES_PER_TEST; $i++) {
            $charA = $this->createFighter('A', 1, $archA, $wA, $offA);
            $charB = $this->createFighter('B', 2, $archB, $wB, $offB);

            $battle = new Battle($i + 1, 1, [$charA, $charB], new Map(10, 10));
            $deadA = false; $deadB = false;

            while (!$battle->isFinished()) {
                $charA->resetRoundState(); $charB->resetRoundState();
                foreach ([$charA, $charB] as $char) {
                    while ($char->canQueueAttack()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK, TargetZone::TORSO));
                        $char->registerAttackUsage(); $char->spendAP(1);
                        if ($char->getId() === 1) $totalAttacksA++; else $totalAttacksB++;
                    }
                    while ($char->canQueueOffhandAttack()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK_OFFHAND, TargetZone::TORSO));
                        $char->registerOffhandAttackUsage(); $char->spendAP(1);
                        if ($char->getId() === 1) $totalAttacksA++; else $totalAttacksB++;
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
                            if ($log->targetId === 1) $parriesA++; else $parriesB++;
                        } elseif ($log->outcome === 'block') {
                            if ($log->targetId === 1) $blocksA++; else $blocksB++;
                        }
                    }
                    if ($log->type === BattleLogType::DEATH) {
                        if ($log->actorId === 1) $deadA = true; else $deadB = true;
                    }
                }
                if (!$battle->isFinished()) $battle->startNewRound();
            }
            if ($deadA && !$deadB) $winsB++; elseif ($deadB && !$deadA) $winsA++; else $draws++;
            $totalRounds += $battle->getRoundNumber();
        }

        $output = sprintf(
            "\n[%s] %s (%s+%s) vs %s (%s+%s)\n" .
            "WINS: A:%d, B:%d, Draws:%d | AvgRounds:%.2f\n" .
            "DEF: A(B:%d, P:%d) | B(B:%d, P:%d)\n",
            $title, $archA, $wA, $offA, $archB, $wB, $offB,
            $winsA, $winsB, $draws, $totalRounds / self::BATTLES_PER_TEST,
            $blocksA, $parriesA, $blocksB, $parriesB
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

    public function test_Matrix_2hAxe_vs_SwordShield() { $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_SWORD', 'SHIELD'); }
    public function test_Matrix_2hAxe_vs_AxeShield()   { $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_AXE', 'SHIELD'); }
    public function test_Matrix_2hAxe_vs_SwordDagger() { $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_SWORD', 'DAGGER'); }
    public function test_Matrix_2hAxe_vs_AxeDagger()   { $this->runMatchupMatrix('2H_AXE', 'NONE', '1H_AXE', 'DAGGER'); }
    public function test_Matrix_AxeDagger_vs_AxeShield()   { $this->runMatchupMatrix('1H_AXE', 'DAGGER', '1H_AXE', 'SHIELD'); }
    public function test_Matrix_SwordDagger_vs_SwordShield() { $this->runMatchupMatrix('1H_SWORD', 'DAGGER', '1H_SWORD', 'SHIELD'); }
}
