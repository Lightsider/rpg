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
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

class CombatBalanceTest extends TestCase
{
    private RoundResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock configs to avoid Facade exceptions if not run in full Laravel environment
        $bpsConfig = new BlockPenetrationConfig(120, 0.95, 0.3);
        $bps = new BlockPenetrationService($bpsConfig);

        $mdsConfig = new MaxDamageConfig(150, 0.80, 0.25);
        $mds = new MaxDamageService($mdsConfig);

        $combatResolver = new CombatResolver($bps, $mds);

        $repoMock = $this->createMock(BattleRepositoryInterface::class);
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds);
    }

    private function createFighter(string $name, int $id, Weapon $weapon, int $x = 0, int $y = 0): Character
    {
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            name: $name,
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 200,
            currentHp: 200, // Make them a bit tankier for more data
            equipment: $equipment,
            maxActionPoints: 3,
            currentActionPoints: 3,
            attackPointsUsed: 0,
            x: $x,
            y: $y,
            blockResistRating: 30 // Give some basic resist to everyone to trigger penetration logic
        );
    }

    #[Group('balance')]
    public function test_sword_vs_axe_balance()
    {
        $totalBattles = 100;

        $swordWins = 0;
        $axeWins = 0;
        $draws = 0;

        $swordDamage = 0;
        $axeDamage = 0;

        $swordHits = 0;
        $axeHits = 0;

        $swordFullBlocks = 0;
        $axeFullBlocks = 0;

        // Sword: Base damage 8-14, 0 acc, 20 block break, +50% pierce dmg, 90 max damage rating
        $swordTemplate = new Weapon(1, 'Sword', 8, 14, DamageType::SLASHING, 0.0, 20, 0.50, 90);

        // Axe: Base damage 8-14, 0 acc, 60 block break, +65% pierce dmg, 0 max damage rating
        $axeTemplate = new Weapon(2, 'Axe', 8, 14, DamageType::CHOPPING, 0.0, 60, 0.65, 0);

        $zones = [
            TargetZone::HEAD,
            TargetZone::TORSO,
            TargetZone::LEGS,
            TargetZone::LEFT_ARM,
            TargetZone::RIGHT_ARM
        ];

        for ($i = 0; $i < $totalBattles; $i++) {
            $swordFighter = $this->createFighter('Sword', 1, $swordTemplate, 0, 0);
            $axeFighter = $this->createFighter('Axe', 2, $axeTemplate, 1, 0); // Start adjacent

            $battle = new Battle($i + 1, [$swordFighter, $axeFighter], new Map(10, 10));

            // Loop until battle finishes
            while (!$battle->isFinished()) {
                $swordFighter->resetRoundState();
                $axeFighter->resetRoundState();

                // Simple AI: queue 2 random attacks and 1 random defense. 
                // AP cost: 2 attacks = 2 AP, 1 defense = 1 AP -> 3 AP total.

                // Sword actions
                $swordTarget1 = $zones[array_rand($zones)];
                $swordTarget2 = $zones[array_rand($zones)];
                $swordDefend = $zones[array_rand($zones)];

                $battle->queueAction(new TurnAction($swordFighter->getId(), ActionType::ATTACK, $swordTarget1));
                $swordFighter->registerAttackUsage();
                $swordFighter->spendAP(1);

                $battle->queueAction(new TurnAction($swordFighter->getId(), ActionType::ATTACK, $swordTarget2));
                $swordFighter->registerAttackUsage();
                $swordFighter->spendAP(1);

                $battle->queueAction(new TurnAction($swordFighter->getId(), ActionType::DEFEND, $swordDefend));
                $swordFighter->spendAP(1);

                $swordFighter->commit();

                // Axe actions
                $axeTarget1 = $zones[array_rand($zones)];
                $axeTarget2 = $zones[array_rand($zones)];
                $axeDefend = $zones[array_rand($zones)];

                $battle->queueAction(new TurnAction($axeFighter->getId(), ActionType::ATTACK, $axeTarget1));
                $axeFighter->registerAttackUsage();
                $axeFighter->spendAP(1);

                $battle->queueAction(new TurnAction($axeFighter->getId(), ActionType::ATTACK, $axeTarget2));
                $axeFighter->registerAttackUsage();
                $axeFighter->spendAP(1);

                $battle->queueAction(new TurnAction($axeFighter->getId(), ActionType::DEFEND, $axeDefend));
                $axeFighter->spendAP(1);

                $axeFighter->commit();

                // Resolve Round
                // This updates HP, kills, etc.
                $result = $this->resolver->resolve($battle);

                // Inspect logs for metrics
                foreach ($result->logs as $log) {
                    if ($log->type === BattleLogType::HIT && $log->damage !== null) {
                        if ($log->actorId === 1) {
                            $swordHits++;
                            $swordDamage += $log->damage;
                        } else {
                            $axeHits++;
                            $axeDamage += $log->damage;
                        }
                    } elseif ($log->type === BattleLogType::BLOCK) {
                        // Actor is defender on block
                        if ($log->actorId === 1) {
                            $swordFullBlocks++;
                        } else {
                            $axeFullBlocks++;
                        }
                    }
                }

                if (!$battle->isFinished()) {
                    $battle->startNewRound();
                }
            }

            // Check winner
            $swordDead = $swordFighter->getCurrentHp() <= 0;
            $axeDead = $axeFighter->getCurrentHp() <= 0;

            if ($swordDead && !$axeDead) {
                $axeWins++;
            } elseif ($axeDead && !$swordDead) {
                $swordWins++;
            } else {
                $draws++;
            }
        }

        $output = sprintf(
            "\n==============================================\n" .
            "       BATTLE SIMULATION RESULTS (%d)       \n" .
            "==============================================\n" .
            "WINS\n" .
            "  Sword: %d\n" .
            "  Axe:   %d\n" .
            "  Draws: %d\n\n" .
            "OFFENSE\n" .
            "  Sword Hits:   %d\n" .
            "  Axe Hits:     %d\n" .
            "  Sword Damage: %d (%d/hit avg)\n" .
            "  Axe Damage:   %d (%d/hit avg)\n\n" .
            "DEFENSE (Successful Full Blocks)\n" .
            "  Sword Blocks: %d\n" .
            "  Axe Blocks:   %d\n" .
            "==============================================\n",
            $totalBattles,
            $swordWins,
            $axeWins,
            $draws,
            $swordHits,
            $axeHits,
            $swordDamage,
            $swordHits > 0 ? (int) ($swordDamage / $swordHits) : 0,
            $axeDamage,
            $axeHits > 0 ? (int) ($axeDamage / $axeHits) : 0,
            $swordFullBlocks,
            $axeFullBlocks
        );

        fwrite(STDOUT, $output);

        $this->assertTrue(true); // Dummy assertion to fulfill PHPUnit test requirement
    }
}
