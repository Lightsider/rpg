<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Map;
use App\Domain\Npc\Behavior\DefensiveBehavior;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DefensiveBehaviorTest extends TestCase
{
    private DefensiveBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new DefensiveBehavior();
    }

    private function createMockCombatant(int $id, int $x, int $y, int $hp, int $team, bool $isNpc = false): MockObject&Combatant
    {
        $mock = $this->createMock(Combatant::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getX')->willReturn($x);
        $mock->method('getY')->willReturn($y);
        $mock->method('getCurrentHp')->willReturn($hp);
        $mock->method('isNpc')->willReturn($isNpc);
        $mock->method('getMaxHp')->willReturn(100);
        
        // Needed for heuristics calculation to not crash
        $weaponMock = $this->createMock(\App\Domain\Weapon\Weapon::class);
        $weaponMock->method('getMaxDamage')->willReturn(10.0);
        
        $mock->method('getWeaponForCombat')->willReturn($weaponMock);
        $mock->method('getSealsBaseDamage')->willReturn(0.0);
        $mock->method('calculateStrengthBonus')->willReturn(0.0);
        $mock->method('calculateDodgeChance')->willReturn(0.0);
        $mock->method('calculateParryChance')->willReturn(0.0);
        
        return $mock;
    }

    public function test_defensive_behavior_1v2_allocates_1_attack_and_3_blocks(): void
    {
        // NPC is at (1,1). Enemies are at (1,0) and (0,1) -> 2 adjacent enemies.
        $npc = $this->createMockCombatant(-1, 1, 1, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getBonusDefensiveAP')->willReturn(1); // Shield
        $npc->method('getMaxAttacks')->willReturn(2);
        
        $enemy1 = $this->createMockCombatant(1, 1, 0, 100, 1);
        $enemy2 = $this->createMockCombatant(2, 0, 1, 100, 1);

        $map = new Map(5, 5);
        $battle = $this->createMock(Battle::class);
        $battle->method('getMap')->willReturn($map);
        $battle->method('getParticipants')->willReturn([$npc, $enemy1, $enemy2]);
        
        $battle->method('getParticipantTeam')->willReturnMap([
            [-1, '2'],
            [1, '1'],
            [2, '1'],
        ]);

        $actions = $this->behavior->decide($npc, $battle);

        $attackCount = 0;
        $blockCount = 0;
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::ATTACK) {
                $attackCount++;
            } elseif ($action->getType() === ActionType::DEFEND) {
                $blockCount++;
            }
        }

        // Expected: 1 attack, 3 blocks (since AP = 3, bonus = 1, attack takes 1 AP, leaving 3 AP for blocks)
        $this->assertEquals(1, $attackCount);
        $this->assertEquals(3, $blockCount);
    }

    public function test_defensive_behavior_1v1_allocates_up_to_2_attacks(): void
    {
        // NPC is at (1,1). Enemy is at (1,0) -> 1 adjacent enemy.
        $npc = $this->createMockCombatant(-1, 1, 1, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getBonusDefensiveAP')->willReturn(1);
        $npc->method('getMaxAttacks')->willReturn(2);
        
        $enemy = $this->createMockCombatant(1, 1, 0, 100, 1);

        $map = new Map(5, 5);
        $battle = $this->createMock(Battle::class);
        $battle->method('getMap')->willReturn($map);
        $battle->method('getParticipants')->willReturn([$npc, $enemy]);
        
        $battle->method('getParticipantTeam')->willReturnMap([
            [-1, '2'],
            [1, '1'],
        ]);

        $actions = $this->behavior->decide($npc, $battle);

        $attackCount = 0;
        $blockCount = 0;
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::ATTACK) {
                $attackCount++;
            } elseif ($action->getType() === ActionType::DEFEND) {
                $blockCount++;
            }
        }

        // Expected: 2 attacks, 2 blocks (AP = 3 base, minus 2 attacks = 1 base leftover + 1 bonus = 2 blocks)
        $this->assertEquals(2, $attackCount);
        $this->assertEquals(2, $blockCount);
    }

    public function test_defensive_behavior_moves_back_when_hp_is_low(): void
    {
        // NPC is at (1,1) with low HP (10 / 100). Target is at (1,0).
        $npc = $this->createMockCombatant(-1, 1, 1, 10, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getBonusDefensiveAP')->willReturn(1);
        $npc->method('getMaxAttacks')->willReturn(2);

        $enemy = $this->createMockCombatant(1, 1, 0, 100, 1);

        $map = new Map(5, 5);
        $battle = $this->createMock(Battle::class);
        $battle->method('getMap')->willReturn($map);
        $battle->method('getParticipants')->willReturn([$npc, $enemy]);
        
        $battle->method('getParticipantTeam')->willReturnMap([
            [-1, '2'],
            [1, '1'],
        ]);

        $actions = $this->behavior->decide($npc, $battle);

        $moveAction = null;
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $moveAction = $action;
                break;
            }
        }

        $this->assertNotNull($moveAction, "NPC should move back when HP is low.");
        $this->assertEquals(1, $moveAction->getFromX());
        $this->assertEquals(1, $moveAction->getFromY());
        // Cell should be non-adjacent to the target (1,0). E.g. (1,2) or (2,2)
        $this->assertFalse($map->isAdjacent($moveAction->getToX(), $moveAction->getToY(), 1, 0));
    }

    public function test_defensive_behavior_does_not_attack_after_movement(): void
    {
        // NPC is at (3,3) (not adjacent to target at (1,1) -> will move).
        $npc = $this->createMockCombatant(-1, 3, 3, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getBonusDefensiveAP')->willReturn(1);
        $npc->method('getMaxAttacks')->willReturn(2);

        $enemy = $this->createMockCombatant(1, 1, 1, 100, 1);

        $map = new Map(5, 5);
        $battle = $this->createMock(Battle::class);
        $battle->method('getMap')->willReturn($map);
        $battle->method('getParticipants')->willReturn([$npc, $enemy]);
        
        $battle->method('getParticipantTeam')->willReturnMap([
            [-1, '2'],
            [1, '1'],
        ]);

        $actions = $this->behavior->decide($npc, $battle);

        $hasMove = false;
        $attackCount = 0;
        $blockCount = 0;

        foreach ($actions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $hasMove = true;
            } elseif ($action->getType() === ActionType::ATTACK || $action->getType() === ActionType::ATTACK_OFFHAND) {
                $attackCount++;
            } elseif ($action->getType() === ActionType::DEFEND) {
                $blockCount++;
            }
        }

        $this->assertTrue($hasMove, "NPC should move to get closer to the target.");
        $this->assertEquals(0, $attackCount, "NPC should NOT attack after moving.");
        // Base AP is 3. Moving takes 1 AP, leaving 2 base AP.
        // Plus 1 bonus defensive AP = 3 blocks total.
        $this->assertEquals(3, $blockCount, "NPC should allocate remaining AP (2 base + 1 defensive bonus) to blocks.");
    }
}
