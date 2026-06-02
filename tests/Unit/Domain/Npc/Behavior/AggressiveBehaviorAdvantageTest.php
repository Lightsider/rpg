<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\Npc\Behavior;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Combatant;
use App\Domain\Battle\Map;
use App\Domain\Npc\Behavior\AggressiveBehavior;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AggressiveBehaviorAdvantageTest extends TestCase
{
    private AggressiveBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->behavior = new AggressiveBehavior();
    }

    private function createMockCombatant(int $id, int $x, int $y, int $hp, int $maxHp, int $team, bool $isNpc = false): MockObject&Combatant
    {
        $mock = $this->createMock(Combatant::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getX')->willReturn($x);
        $mock->method('getY')->willReturn($y);
        $mock->method('getCurrentHp')->willReturn($hp);
        $mock->method('getMaxHp')->willReturn($maxHp);
        $mock->method('isNpc')->willReturn($isNpc);
        
        $weaponMock = $this->createMock(\App\Domain\Weapon\Weapon::class);
        $weaponMock->method('getMaxDamage')->willReturn(10.0);
        
        $mock->method('getWeaponForCombat')->willReturn($weaponMock);
        $mock->method('getSealsBaseDamage')->willReturn(0.0);
        $mock->method('calculateStrengthBonus')->willReturn(0.0);
        $mock->method('calculateDodgeChance')->willReturn(0.0);
        $mock->method('calculateParryChance')->willReturn(0.0);
        
        return $mock;
    }

    public function test_aggressive_behavior_does_not_move_when_2v1_and_adjacent(): void
    {
        // NPC is at (1,1). Enemy is at (1,0).
        // Teammate is at (0,1).
        $npc = $this->createMockCombatant(-1, 1, 1, 100, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getMaxAttacks')->willReturn(2);
        
        $enemy = $this->createMockCombatant(1, 1, 0, 100, 100, 1);
        $teammate = $this->createMockCombatant(2, 0, 1, 100, 100, 2);

        $map = new Map(5, 5);
        $battle = $this->createMock(Battle::class);
        $battle->method('getMap')->willReturn($map);
        $battle->method('getParticipants')->willReturn([$npc, $enemy, $teammate]);
        
        $battle->method('getParticipantTeam')->willReturnMap([
            [-1, '2'],
            [1, '1'],
            [2, '2'],
        ]);

        $actions = $this->behavior->decide($npc, $battle);

        $moveAction = null;
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $moveAction = $action;
                break;
            }
        }

        $this->assertNull($moveAction, "NPC should NOT move when it has a 2v1 advantage and is adjacent.");
    }
}
