<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleLogType;
use App\Domain\Battle\CombatResolver;
use App\Domain\Battle\Map;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\RoundResolver;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Services\MovementResolver;
use App\Domain\Character\Character;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use PHPUnit\Framework\TestCase;

class RoundResolverTest extends TestCase
{
    private function createCharacter(int $id, int $x = 0, int $y = 0): Character
    {
        $weapon = new Weapon(1, "Sword", 10, 10, DamageType::SLASHING, 0, 0, 0.0);
        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            userId: $id,
            name: "Char$id",
            strength: 0,
            agility: 0,
            constitution: 0,
            wit: 0,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            x: $x,
            y: $y
        );
    }

    private function makeResolver(): RoundResolver
    {
        $battleRepository = $this->createMock(BattleRepositoryInterface::class);
        $combatResolver = $this->createMock(CombatResolver::class);
        $blockPenetrationService = $this->createMock(BlockPenetrationService::class);
        $maxDamageService = $this->createMock(MaxDamageService::class);
        $movementResolver = $this->createMock(MovementResolver::class);

        return new RoundResolver($combatResolver, $battleRepository, $blockPenetrationService, $maxDamageService, $movementResolver);
    }

    public function test_battle_logs_with_value_objects(): void
    {
        $char1 = $this->createCharacter(1, 0, 0);
        $char2 = $this->createCharacter(2, 1, 0); // Adjacent
        $map = new Map(10, 10);
        $battle = new Battle(1, 1, [$char1, $char2], $map);

        // Char1 moves (valid distance)
        $battle->queueAction(new TurnAction(1, ActionType::MOVE, null, 0, 0, 0, 1));

        // Char1 attacks HEAD (remains adjacent at (0,1) and (1,0))
        $battle->queueAction(new TurnAction(1, ActionType::ATTACK, TargetZone::HEAD));

        // Char2 defends HEAD
        $battle->queueAction(new TurnAction(2, ActionType::DEFEND, TargetZone::HEAD));

        $battleRepository = $this->createMock(BattleRepositoryInterface::class);
        $combatResolver = $this->createMock(CombatResolver::class);
        $blockPenetrationService = $this->createMock(BlockPenetrationService::class);
        $maxDamageService = $this->createMock(MaxDamageService::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $resolver = new RoundResolver($combatResolver, $battleRepository, $blockPenetrationService, $maxDamageService, $movementResolver);

        // Mock a hit that lands despite the defense (partial damage)
        $attackResult = new \App\Domain\Battle\AttackResult(10, false, false, false, DamageType::SLASHING);
        $combatResolver->method('resolveAttack')->willReturn($attackResult);

        $result = $resolver->resolve($battle);

        // Expected Logs:
        // 1. MOVE (Char1)
        // 2. HIT (Char1 hit Char2 in HEAD for 10)

        $this->assertCount(2, $result->logs);

        $moveLog = $result->logs[0];
        $this->assertEquals(BattleLogType::MOVE, $moveLog->type);
        $this->assertEquals(1, $moveLog->actorId);

        $hitLog = $result->logs[1];
        $this->assertEquals(BattleLogType::HIT, $hitLog->type);
        $this->assertEquals(1, $hitLog->actorId);
        $this->assertEquals(2, $hitLog->targetId);
        $this->assertEquals(TargetZone::HEAD, $hitLog->zone);
        $this->assertEquals(10, $hitLog->damage);
    }

    public function test_block_log_actor(): void
    {
        $char1 = $this->createCharacter(1, 0, 0);
        $char2 = $this->createCharacter(2, 1, 0);
        $map = new Map(10, 10);
        $battle = new Battle(1, 1, [$char1, $char2], $map);

        $battle->queueAction(new TurnAction(1, ActionType::ATTACK, TargetZone::HEAD));
        $battle->queueAction(new TurnAction(2, ActionType::DEFEND, TargetZone::HEAD));

        $battleRepository = $this->createMock(BattleRepositoryInterface::class);
        $combatResolver = $this->createMock(CombatResolver::class);
        $blockPenetrationService = $this->createMock(BlockPenetrationService::class);
        $maxDamageService = $this->createMock(MaxDamageService::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $resolver = new RoundResolver($combatResolver, $battleRepository, $blockPenetrationService, $maxDamageService, $movementResolver);

        // Mock total block (0 damage)
        $attackResult = new \App\Domain\Battle\AttackResult(0, false, false, false, DamageType::SLASHING);
        $combatResolver->method('resolveAttack')->willReturn($attackResult);

        $result = $resolver->resolve($battle);

        $blockLog = $result->logs[0];
        $this->assertEquals(BattleLogType::BLOCK, $blockLog->type);
        $this->assertEquals(2, $blockLog->actorId); // Defender is the actor of the block
        $this->assertEquals(1, $blockLog->targetId); // Attacker
        $this->assertEquals(TargetZone::HEAD, $blockLog->zone);
    }

    public function test_death_log(): void
    {
        $char1 = $this->createCharacter(1, 0, 0);
        $char2 = $this->createCharacter(2, 1, 0);
        $char2->setCurrentHp(5); // Low HP

        $map = new Map(10, 10);
        $battle = new Battle(1, 1, [$char1, $char2], $map);

        $battle->queueAction(new TurnAction(1, ActionType::ATTACK, TargetZone::HEAD));

        $resolver = $this->makeResolver();

        // Overwrite the combatResolver mock on the fresh resolver via re-creation
        $battleRepository = $this->createMock(BattleRepositoryInterface::class);
        $combatResolver = $this->createMock(CombatResolver::class);
        $blockPenetrationService = $this->createMock(BlockPenetrationService::class);
        $maxDamageService = $this->createMock(MaxDamageService::class);
        $movementResolver = $this->createMock(MovementResolver::class);
        $resolver = new RoundResolver($combatResolver, $battleRepository, $blockPenetrationService, $maxDamageService, $movementResolver);

        $attackResult = new \App\Domain\Battle\AttackResult(10, false, false, false, DamageType::SLASHING);
        $combatResolver->method('resolveAttack')->willReturn($attackResult);

        $result = $resolver->resolve($battle);

        $deathLog = null;
        foreach ($result->logs as $log) {
            if ($log->type === BattleLogType::DEATH) {
                $deathLog = $log;
                break;
            }
        }

        $this->assertNotNull($deathLog);
        $this->assertEquals(2, $deathLog->actorId); // Victim is the actor of death event
    }
}



