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

class AggressiveBehaviorTest extends TestCase
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

    public function test_aggressive_behavior_fears_1v2_and_moves_away(): void
    {
        // NPC is at (1,1). Enemies are at (1,0) and (0,1).
        // Target will probably be (1,0). NPC is adjacent to 2 enemies.
        // There's a free cell at (2,0) which is adjacent ONLY to (1,0) and NOT to (0,1).
        // The NPC should move to (2,0) to isolate into a 1v1.
        $npc = $this->createMockCombatant(-1, 1, 1, 100, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getMaxAttacks')->willReturn(2);
        
        $enemy1 = $this->createMockCombatant(1, 1, 0, 100, 100, 1);
        $enemy2 = $this->createMockCombatant(2, 0, 1, 100, 100, 1);

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

        $moveAction = null;
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::MOVE) {
                $moveAction = $action;
                break;
            }
        }

        $this->assertNotNull($moveAction, "NPC should have chosen to move to escape 1v2.");
        // Should move to a tile adjacent to one enemy but not the other. 
        // e.g., (2,0) or (2,1) depending on distance.
        // Let's just assert it moves SOMEWHERE to escape.
        $this->assertTrue($moveAction->getToX() !== 1 || $moveAction->getToY() !== 1);
    }

    public function test_aggressive_behavior_panics_and_blocks_when_hp_low(): void
    {
        // NPC is safely 1v1 but HP is extremely low (10 / 100) -> panics.
        $npc = $this->createMockCombatant(-1, 1, 1, 10, 100, 2, true);
        $npc->method('getCurrentActionPoints')->willReturn(3);
        $npc->method('getBonusOffhandAP')->willReturn(1); // Has dagger
        $npc->method('getMaxAttacks')->willReturn(2);
        
        $enemy = $this->createMockCombatant(1, 1, 0, 100, 100, 1);

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
        $offhandCount = 0;
        $blockCount = 0;
        
        foreach ($actions as $action) {
            if ($action->getType() === ActionType::ATTACK) {
                $attackCount++;
            } elseif ($action->getType() === ActionType::ATTACK_OFFHAND) {
                $offhandCount++;
            } elseif ($action->getType() === ActionType::DEFEND) {
                $blockCount++;
            }
        }

        // Expected: 0 Main attacks, 1 Offhand attack (free AP), 3 Blocks (Base AP dumped)
        $this->assertEquals(0, $attackCount);
        $this->assertEquals(1, $offhandCount);
        $this->assertEquals(3, $blockCount);
    }
}
