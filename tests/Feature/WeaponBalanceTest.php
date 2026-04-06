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
use App\Domain\Item\Item;
use App\Services\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\WeaponArchetype;
use App\Domain\Shield\Shield;
use PHPUnit\Framework\TestCase;

class WeaponBalanceTest extends TestCase
{
    private RoundResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $bpsConfig = new BlockPenetrationConfig(120, 0.05, 0.95, 0.20);
        $bps = new BlockPenetrationService($bpsConfig);

        $mdsConfig = new MaxDamageConfig(300, 0.80, 0.20);
        $mds = new MaxDamageService($mdsConfig);

        $combatResolver = new CombatResolver($bps, $mds);

        $repoMock = $this->createMock(BattleRepositoryInterface::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver);
    }

    private function createFighter(string $name, int $id, Weapon $weapon, ?Item $offHand = null, int $x = 0, int $y = 0): Character
    {
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        if ($offHand) {
            $equipment->setItem(EquipmentSlot::OFF_HAND, $offHand);
        }

        $maxHp = (int) ceil(40 + (8 * 8.5));

        $char = new Character(
            id: $id,
            userId: $id,
            name: $name,
            strength: 8,
            agility: 0,
            constitution: 8,
            wit: 0,
            maxHp: $maxHp,
            currentHp: 0, // Temporary
            equipment: $equipment,
            maxActionPoints: 3,
            currentActionPoints: 3,
            attackPointsUsed: 0,
            x: $x,
            y: $y,
            blockResistRating: 0
        );

        // Reflection or setter to set currentHp to the dynamic maxHp if needed,
        // but easier to just use a temporary then call restoreHp if available.
        // Actually, let's just use the constructor properly by calculating the boost here
        // or just fixing the constructor in Character to handle '0' as 'max'.
        
        // Let's just calculate the expected final HP for the constructor to keep it simple.
        $finalMaxHp = $char->getMaxHp();
        
        return new Character(
            id: $id,
            userId: $id,
            name: $name,
            strength: 8,
            agility: 0,
            constitution: 8,
            wit: 0,
            maxHp: $maxHp,
            currentHp: $finalMaxHp,
            equipment: $equipment,
            maxActionPoints: 3,
            currentActionPoints: 3,
            attackPointsUsed: 0,
            x: $x,
            y: $y,
            blockResistRating: 0
        );
    }

    public function test_sword_vs_axe_balance()
    {
        $swordTemplate = new Weapon(1, 'Sword', 9, 11, DamageType::SLASHING, 0.0, 20, 0.50, 75);
        $axeTemplate = new Weapon(2, 'Axe', 9, 11, DamageType::CHOPPING, 0.0, 70, 0.65, 0);

        $this->runSimulation('SWORD VS AXE', $swordTemplate, null, $axeTemplate, null);
    }

    public function test_two_handed_axe_vs_sword_shield()
    {
        $twoHandedAxe = new Weapon(
            id: 5,
            name: 'Great Axe',
            minDamage: 11.7,
            maxDamage: 14.3,
            damageType: DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: 120,
            pierceMultiplier: 0.75,
            maxDamageRating: 75,
            archetype: WeaponArchetype::STABLE,
            isTwoHanded: true
        );

        $swordTemplate = new Weapon(1, 'Sword', 9, 11, DamageType::SLASHING, 0.0, 20, 0.50, 75);
        $shield = new Shield(3, 'Buckler', 40, 0.10);

        $this->runSimulation('2H AXE VS SWORD+SHIELD', $twoHandedAxe, null, $swordTemplate, $shield);
    }

    public function test_two_handed_axe_vs_axe_shield()
    {
        $twoHandedAxe = new Weapon(
            id: 5,
            name: 'Great Axe',
            minDamage: 11.7,
            maxDamage: 14.3,
            damageType: DamageType::CHOPPING,
            accuracyBonus: 0.0,
            blockBreakRating: 120,
            pierceMultiplier: 0.75,
            maxDamageRating: 75,
            archetype: WeaponArchetype::STABLE,
            isTwoHanded: true
        );

        $axeTemplate = new Weapon(2, 'Axe', 9, 11, DamageType::CHOPPING, 0.0, 70, 0.65, 0);
        $shield = new Shield(3, 'Buckler', 40, 0.10);

        $this->runSimulation('2H AXE VS AXE+SHIELD', $twoHandedAxe, null, $axeTemplate, $shield);
    }

    private function runSimulation(string $title, Weapon $wA, ?Item $offA, Weapon $wB, ?Item $offB): void
    {
        $totalBattles = 1000;
        $winsA = 0; $winsB = 0; $draws = 0;
        $damageA = 0; $damageB = 0;
        $hitsA = 0; $hitsB = 0;
        $blocksA = 0; $blocksB = 0;
        $brokenA = 0; $brokenB = 0;
        
        $totalAttacksA = 0; $totalAttacksB = 0;
        $blockAttemptsA = 0; $blockAttemptsB = 0;

        $zones = [TargetZone::HEAD, TargetZone::TORSO, TargetZone::LEGS, TargetZone::LEFT_ARM, TargetZone::RIGHT_ARM];

        for ($i = 0; $i < $totalBattles; $i++) {
            $charA = $this->createFighter('A', 1, $wA, $offA, 0, 0);
            $charB = $this->createFighter('B', 2, $wB, $offB, 1, 0);

            $battle = new Battle($i + 1, 1, [$charA, $charB], new Map(10, 10));
            $deadA = false; $deadB = false;

            while (!$battle->isFinished()) {
                $charA->resetRoundState(); $charB->resetRoundState();

                // Queue Actions (Up to 2 Atk, then as many Def as AP allows)
                foreach ([$charA, $charB] as $char) {
                    while ($char->canQueueAttack()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::ATTACK, $zones[array_rand($zones)]));
                        $char->registerAttackUsage();
                        $char->spendAP(1);
                        if ($char->getId() === 1) { $totalAttacksA++; } else { $totalAttacksB++; }
                    }
                    while ($char->canQueueDefense()) {
                        $battle->queueAction(new TurnAction($char->getId(), ActionType::DEFEND, $zones[array_rand($zones)]));
                        $char->spendAP(1);
                    }
                    $char->commit();
                }

                $result = $this->resolver->resolve($battle);
                foreach ($result->logs as $log) {
                    if ($log->type === BattleLogType::ATTACK) {
                        // Count if the attack HIT a defended zone (regardless of outcome)
                        // This logic relies on the fact that 'isBlocked' in RoundResolver determines the 'block'/'block_break' outcomes.
                        if (in_array($log->outcome, ['block', 'block_break'], true)) {
                            if ($log->targetId === 1) { $blockAttemptsA++; } else { $blockAttemptsB++; }
                        }

                        if (in_array($log->outcome, ['hit', 'block_break'], true) && $log->damage !== null) {
                            if ($log->actorId === 1) { $hitsA++; $damageA += $log->damage; }
                            else { $hitsB++; $damageB += $log->damage; }
                        }
                        
                        if ($log->outcome === 'block') {
                            if ($log->targetId === 1) { $blocksA++; } else { $blocksB++; }
                        } elseif ($log->outcome === 'block_break') {
                            if ($log->actorId === 1) { $brokenA++; } else { $brokenB++; }
                        }
                    }
                    if ($log->type === BattleLogType::DEATH) {
                        if ($log->actorId === 1) { $deadA = true; }
                        elseif ($log->actorId === 2) { $deadB = true; }
                    }
                }
                if (!$battle->isFinished()) { $battle->startNewRound(); }
            }

            if ($deadA && !$deadB) { $winsB++; }
            elseif ($deadB && !$deadA) { $winsA++; }
            else { $draws++; }
        }

        $output = sprintf(
            "\n==============================================\n" .
            "       %s (%d)       \n" .
            "==============================================\n" .
            "WINS\n" .
            "  A (%s, Break:%d): %d\n" .
            "  B (%s, Break:%d): %d\n" .
            "  Draws: %d\n\n" .
            "OFFENSE\n" .
            "  A Attacks: %d | Hits Defended: %d | Breaks: %d (%d%% break rate)\n" .
            "  B Attacks: %d | Hits Defended: %d | Breaks: %d (%d%% break rate)\n" .
            "  A Damage:  %d (%d/hit avg)\n" .
            "  B Damage:  %d (%d/hit avg)\n\n" .
            "DEFENSE (Successful Full Blocks)\n" .
            "  A Blocks: %d\n" .
            "  B Blocks: %d\n" .
            "==============================================\n",
            $title, $totalBattles,
            $wA->getName(), $wA->getBlockBreakRating(), $winsA,
            $wB->getName() . ($offB ? " + Shield" : ""), $wB->getBlockBreakRating(), $winsB,
            $draws,
            $totalAttacksA, $blockAttemptsB, $brokenA, $blockAttemptsB > 0 ? (int)($brokenA / $blockAttemptsB * 100) : 0,
            $totalAttacksB, $blockAttemptsA, $brokenB, $blockAttemptsA > 0 ? (int)($brokenB / $blockAttemptsA * 100) : 0,
            $damageA, $hitsA > 0 ? (int) ($damageA / $hitsA) : 0,
            $damageB, $hitsB > 0 ? (int) ($damageB / $hitsB) : 0,
            $blocksA, $blocksB
        );

        fwrite(STDOUT, $output);
        $this->assertTrue(true);
    }
}
