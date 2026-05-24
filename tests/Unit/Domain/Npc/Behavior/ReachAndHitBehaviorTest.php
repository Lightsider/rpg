<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Map;
use App\Domain\Npc\Behavior\ReachAndHitBehavior;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ReachAndHitBehaviorTest extends TestCase
{
    private ReachAndHitBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new ReachAndHitBehavior();
    }

    private function createMockCombatant(int $id, int $x, int $y, int $hp, int $team, bool $isNpc = false): MockObject&Combatant
    {
        $mock = $this->createMock(Combatant::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getX')->willReturn($x);
        $mock->method('getY')->willReturn($y);
        $mock->method('getCurrentHp')->willReturn($hp);
        $mock->method('isNpc')->willReturn($isNpc);
        
        $weaponMock = $this->createMock(\App\Domain\Weapon\Weapon::class);
        $weaponMock->method('getMaxDamage')->willReturn(10.0);
        
        $mock->method('getWeaponForCombat')->willReturn($weaponMock);
        $mock->method('getSealsBaseDamage')->willReturn(0.0);
        
        return $mock;
    }

    public function test_reach_and_hit_behavior_does_not_attack_after_movement(): void
    {
        // NPC is at (3,3) (not adjacent to target at (1,1) -> will move).
        $npc = $this->createMockCombatant(-1, 3, 3, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
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
            } elseif ($action->getType() === ActionType::ATTACK) {
                $attackCount++;
            } elseif ($action->getType() === ActionType::DEFEND) {
                $blockCount++;
            }
        }

        $this->assertTrue($hasMove, "NPC should move to get closer to the target.");
        $this->assertEquals(0, $attackCount, "NPC should NOT attack after moving.");
        // Base AP is 3. Moving takes 1 AP, leaving 2 base AP which should go to blocks.
        $this->assertEquals(2, $blockCount, "NPC should allocate remaining AP (2) to blocks.");
    }
}
