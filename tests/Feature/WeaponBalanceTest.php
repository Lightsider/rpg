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
use PHPUnit\Framework\TestCase;

class WeaponBalanceTest extends TestCase
{
    private RoundResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock configs to avoid Facade exceptions if not run in full Laravel environment
        $bpsConfig = new BlockPenetrationConfig(120, 0.95, 0.20);
        $bps = new BlockPenetrationService($bpsConfig);

        $mdsConfig = new MaxDamageConfig(300, 0.80, 0.20);
        $mds = new MaxDamageService($mdsConfig);

        $combatResolver = new CombatResolver($bps, $mds);

        $repoMock = $this->createMock(BattleRepositoryInterface::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $this->resolver = new RoundResolver($combatResolver, $repoMock, $bps, $mds, $movementResolver);
    }

    private function createFighter(string $name, int $id, Weapon $weapon, int $x = 0, int $y = 0): Character
    {
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        $maxHp = (int) ceil(40 + (8 * 8.5));

        return new Character(
            id: $id,
            userId: $id,
            name: $name,
            strength: 8,
            agility: 0,
            constitution: 8,
            wit: 0,
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

    public function test_sword_vs_axe_balance()
    {
        $totalBattles = 1000;

        $swordWins = 0;
        $axeWins = 0;
        $draws = 0;

        $swordDamage = 0;
        $axeDamage = 0;

        $swordHits = 0;
        $axeHits = 0;

        $swordFullBlocks = 0;
        $axeFullBlocks = 0;

        // Sword: Base damage 9-11, 0 acc, 20 block break, +50% pierce dmg, 75 max damage rating
        $swordTemplate = new Weapon(1, 'Sword', 9, 11, DamageType::SLASHING, 0.0, 20, 0.50, 75);

        // Axe: Base damage 9-11, 0 acc, 60 block break, +65% pierce dmg, 0 max damage rating
        $axeTemplate = new Weapon(2, 'Axe', 9, 11, DamageType::CHOPPING, 0.0, 60, 0.65, 0);

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

            $battle = new Battle($i + 1, 1, [$swordFighter, $axeFighter], new Map(10, 10));

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

                $battle->queueAction(new TurnAction(
                    characterId: $swordFighter->getId(),
                    type: ActionType::ATTACK,
                    targetZone: $swordTarget1
                ));
                $swordFighter->registerAttackUsage();
                $swordFighter->spendAP(1);

                $battle->queueAction(new TurnAction(
                    characterId: $swordFighter->getId(),
                    type: ActionType::ATTACK,
                    targetZone: $swordTarget2
                ));
                $swordFighter->registerAttackUsage();
                $swordFighter->spendAP(1);

                $battle->queueAction(new TurnAction(
                    characterId: $swordFighter->getId(),
                    type: ActionType::DEFEND,
                    targetZone: $swordDefend
                ));
                $swordFighter->spendAP(1);

                $swordFighter->commit();

                // Axe actions
                $axeTarget1 = $zones[array_rand($zones)];
                $axeTarget2 = $zones[array_rand($zones)];
                $axeDefend = $zones[array_rand($zones)];

                $battle->queueAction(new TurnAction(
                    characterId: $axeFighter->getId(),
                    type: ActionType::ATTACK,
                    targetZone: $axeTarget1
                ));
                $axeFighter->registerAttackUsage();
                $axeFighter->spendAP(1);

                $battle->queueAction(new TurnAction(
                    characterId: $axeFighter->getId(),
                    type: ActionType::ATTACK,
                    targetZone: $axeTarget2
                ));
                $axeFighter->registerAttackUsage();
                $axeFighter->spendAP(1);

                $battle->queueAction(new TurnAction(
                    characterId: $axeFighter->getId(),
                    type: ActionType::DEFEND,
                    targetZone: $axeDefend
                ));
                $axeFighter->spendAP(1);

                $axeFighter->commit();

                // Resolve Round
                // This updates HP, kills, etc.
                $result = $this->resolver->resolve($battle);

                // Inspect logs for metrics
                foreach ($result->logs as $log) {
                    if ($log->type === BattleLogType::ATTACK) {
                        if (in_array($log->outcome, ['hit', 'block_break'], true) && $log->damage !== null) {
                            if ($log->actorId === 1) {
                                $swordHits++;
                                $swordDamage += $log->damage;
                            } else {
                                $axeHits++;
                                $axeDamage += $log->damage;
                            }
                        } elseif ($log->outcome === 'block') {
                            if ($log->targetId === 1) {
                                $swordFullBlocks++;
                            } else {
                                $axeFullBlocks++;
                            }
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
