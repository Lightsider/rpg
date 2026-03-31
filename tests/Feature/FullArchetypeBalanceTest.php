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
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
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
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver);
    }

    // =========================================================================
    // Stats Builders
    // =========================================================================

    private function getTankStats(): array { return ['str' => 10, 'con' => 10, 'dex' => 0, 'wit' => 0]; }
    private function getCritStats(): array { return ['str' => 5, 'con' => 10, 'dex' => 0, 'wit' => 5]; }
    private function getUniStats(): array { return ['str' => 7, 'con' => 10, 'dex' => 0, 'wit' => 3]; }
    private function getDodgeStats(): array { return ['str' => 0, 'con' => 10, 'dex' => 10, 'wit' => 0]; } // Extreme dodge stats if needed

    // =========================================================================
    // Gear Builders (Matching ItemSeeder)
    // =========================================================================

    private function createTankGear(bool $isSword): array
    {
        $weapon = new Weapon(
            id: $isSword ? 1 : 2,
            name: $isSword ? 'Guardian Sword' : 'Guardian Axe',
            minDamage: 9.0,
            maxDamage: 11.0,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: $isSword ? 20 : 60,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $isSword ? 90 : 0,
            archetype: WeaponArchetype::TANK,
            requiredStrength: 10
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(7, 'Guardian Seal', 0.6750, 0.8250, 0.0, WeaponArchetype::TANK, 10);
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
            maxDamageRating: $isSword ? 90 : 0,
            flatCritBonus: 10.0,
            critChanceBonus: 5.0,
            archetype: WeaponArchetype::CRIT,
            requiredStrength: 5,
            requiredWit: 5
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(8, 'Executioner Seal', 0.5, 0.65, 0.80, 1.0, WeaponArchetype::CRIT, 5, 5);
        }

        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createUniGear(bool $isSword): array
    {
        $weapon = new Weapon(
            id: $isSword ? 5 : 6,
            name: $isSword ? 'Balanced Sword' : 'Balanced Axe',
            minDamage: 8.5,
            maxDamage: 10.5,
            damageType: $isSword ? DamageType::SLASHING : DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: $isSword ? 20 : 60,
            pierceMultiplier: $isSword ? 0.5 : 0.65,
            maxDamageRating: $isSword ? 90 : 0,
            flatCritBonus: 4.0,
            critChanceBonus: 2.0,
            archetype: WeaponArchetype::UNIVERSAL,
            requiredStrength: 7,
            requiredWit: 3
        );

        $seals = [];
        for ($i = 0; $i < 4; $i++) {
            $seals[] = new Seal(9, 'Balanced Seal', 0.6, 0.75, 0.3, 0.5, WeaponArchetype::UNIVERSAL, 7, 3);
        }

        return ['weapon' => $weapon, 'seals' => $seals];
    }

    private function createTankArmor(): array
    {
        return [
            new Armor(10, 'Guardian Helmet', 10.0, 0.0, ArmorSubtype::HELMET, 0, 0, 0, 10),
            new Armor(11, 'Guardian Chestplate', 10.0, 0.0, ArmorSubtype::BODY, 0, 0, 0, 10),
            new Armor(12, 'Guardian Boots', 10.0, 0.0, ArmorSubtype::BOOTS, 0, 0, 0, 10),
            new Armor(13, 'Guardian Gauntlets', 10.0, 0.0, ArmorSubtype::GLOVES, 0, 0, 0, 10),
        ];
    }

    private function createDodgeArmor(): array
    {
        return [
            new Armor(14, 'Shadow Helmet', 0.0, 5.0, ArmorSubtype::HELMET, 0, 0, 5, 5),
            new Armor(15, 'Shadow Chestplate', 0.0, 5.0, ArmorSubtype::BODY, 0, 0, 5, 5),
            new Armor(16, 'Shadow Boots', 0.0, 5.0, ArmorSubtype::BOOTS, 0, 0, 5, 5),
            new Armor(17, 'Shadow Gauntlets', 0.0, 5.0, ArmorSubtype::GLOVES, 0, 0, 5, 5),
        ];
    }

    private function createUniArmor(): array
    {
        return [
            new Armor(18, 'Balanced Helmet', 7.0, 1.5, ArmorSubtype::HELMET, 0, 0, 3, 7),
            new Armor(19, 'Balanced Chestplate', 7.0, 1.5, ArmorSubtype::BODY, 0, 0, 3, 7),
            new Armor(20, 'Balanced Boots', 7.0, 1.5, ArmorSubtype::BOOTS, 0, 0, 3, 7),
            new Armor(21, 'Balanced Gauntlets', 7.0, 1.5, ArmorSubtype::GLOVES, 0, 0, 3, 7),
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

        $maxHp = (int)round(45 + ($stats['con'] * 4.5));

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
            'winRateA' => ($winsA / self::BATTLES_PER_TEST) * 100,
            'winRateB' => ($winsB / self::BATTLES_PER_TEST) * 100,
            'drawRate' => ($draws / self::BATTLES_PER_TEST) * 100,
            'avgRounds' => $totalRounds / self::BATTLES_PER_TEST
        ];
    }

    private function queueActions(Battle $battle, Character $char): void
    {
        $zones = [TargetZone::HEAD, TargetZone::TORSO, TargetZone::LEGS, TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM];
        // 2 random attacks
        for ($i = 0; $i < 2; $i++) {
            $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK, $zones[array_rand($zones)]));
            $char->registerAttackUsage(); $char->spendAP(1);
        }
        // 1 random defense
        $battle->queueAction(new TurnAction($char->getId(), ActionType::DEFEND, $zones[array_rand($zones)]));
        $char->spendAP(1);
        $char->commit();
    }

    private function printResults(string $title, string $matchup, array $results): void
    {
        fwrite(STDOUT, sprintf(
            "[%-30s] %-25s: A:%3d%% | B:%3d%% | D:%2d%% | Rounds:%.1f\n",
            $title, $matchup, $results['winRateA'], $results['winRateB'], $results['drawRate'], $results['avgRounds']
        ));
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
            // Tank vs Crit
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('TankAtk', $id, $this->getTankStats(), $this->createTankGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('CritAtk', $id, $this->getCritStats(), $this->createCritGear($isSword), $fixedArmor),
                'Tank', 'Crit'
            );
            $this->printResults($title, "Tank vs Crit ($wName)", $res);

            // Tank vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('TankAtk', $id, $this->getTankStats(), $this->createTankGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('UniAtk', $id, $this->getUniStats(), $this->createUniGear($isSword), $fixedArmor),
                'Tank', 'Uni'
            );
            $this->printResults($title, "Tank vs Uni ($wName)", $res);

            // Crit vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('CritAtk', $id, $this->getCritStats(), $this->createCritGear($isSword), $fixedArmor),
                fn($id) => $this->createFighter('UniAtk', $id, $this->getUniStats(), $this->createUniGear($isSword), $fixedArmor),
                'Crit', 'Uni'
            );
            $this->printResults($title, "Crit vs Uni ($wName)", $res);
        }
    }

    // =========================================================================
    // DEFENSE BALANCE TESTS (Fixed Attack)
    // =========================================================================

    public function test_defense_balance_vs_tank_attack(): void
    {
        $this->runDefenseFocusTests('Vs Tank Atk', fn($sword) => $this->createTankGear($sword), $this->getTankStats());
        $this->assertTrue(true);
    }

    public function test_defense_balance_vs_crit_attack(): void
    {
        $this->runDefenseFocusTests('Vs Crit Atk', fn($sword) => $this->createCritGear($sword), $this->getCritStats());
        $this->assertTrue(true);
    }

    public function test_defense_balance_vs_universal_attack(): void
    {
        $this->runDefenseFocusTests('Vs Uni Atk', fn($sword) => $this->createUniGear($sword), $this->getUniStats());
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
                fn($id) => $this->createFighter('DodgeDef', $id, $this->getUniStats(), $gear, $this->createDodgeArmor()), // Use Uni stats or 5/10/5/0 usually
                'Tank', 'Dodge'
            );
            $this->printResults($title, "Tank vs Dodge ($wName)", $res);

            // Tank vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('TankDef', $id, $this->getTankStats(), $gear, $this->createTankArmor()),
                fn($id) => $this->createFighter('UniDef', $id, $this->getUniStats(), $gear, $this->createUniArmor()),
                'Tank', 'Uni'
            );
            $this->printResults($title, "Tank vs Uni ($wName)", $res);

            // Dodge vs Uni
            $res = $this->runSimulation(
                fn($id) => $this->createFighter('DodgeDef', $id, $this->getUniStats(), $gear, $this->createDodgeArmor()),
                fn($id) => $this->createFighter('UniDef', $id, $this->getUniStats(), $gear, $this->createUniArmor()),
                'Dodge', 'Uni'
            );
            $this->printResults($title, "Dodge vs Uni ($wName)", $res);
        }
    }
}
