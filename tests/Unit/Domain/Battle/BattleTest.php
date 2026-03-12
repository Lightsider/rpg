<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Battle\ActionType;
use App\Domain\Battle\TurnAction;
use App\Domain\Character\Character;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use App\Domain\DomainException;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class BattleTest extends TestCase
{
    private function createMockCharacter(int $id, int $hp = 100, int $x = 0, int $y = 0): Character
    {
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);
        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            userId: $id,
            name: "Char $id",
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: $hp,
            equipment: $equipment,
            x: $x,
            y: $y
        );
    }

    private function createDefaultMap(): Map
    {
        return new Map(10, 10);
    }

    public function test_initial_state_is_active(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $this->assertEquals(BattleState::ACTIVE, $battle->getState());
        $this->assertEquals(1, $battle->getRoundNumber());
        $this->assertFalse($battle->isFinished());
    }

    public function test_start_resolving_transitions_to_resolving(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $battle->startResolving();
        $this->assertEquals(BattleState::RESOLVING, $battle->getState());
    }

    public function test_start_resolving_fails_if_already_resolving(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $battle->startResolving();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Round can resolve ONLY if state is ACTIVE.');
        $battle->startResolving();
    }

    public function test_finish_resolving_transitions_back_to_active(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $battle->startResolving();
        $battle->finishResolving();

        $this->assertEquals(BattleState::ACTIVE, $battle->getState());
    }

    public function test_finish_resolving_transitions_to_finished_if_one_left_alive(): void
    {
        $char1 = $this->createMockCharacter(1, 100);
        $char2 = $this->createMockCharacter(2, 0); // Already dead

        $battle = new Battle(1, 1, [$char1, $char2], $this->createDefaultMap());

        $battle->startResolving();
        $battle->finishResolving();

        $this->assertEquals(BattleState::FINISHED, $battle->getState());
        $this->assertTrue($battle->isFinished());
    }

    public function test_cannot_commit_if_resolving(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $battle->startResolving();

        $this->expectException(Exception::class);
        $battle->commitCharacter(1);
    }

    public function test_start_new_round_resets_state(): void
    {
        $battle = new Battle(1, 1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ], $this->createDefaultMap());

        $battle->startResolving();
        $battle->finishResolving(); // Back to ACTIVE

        $battle->startNewRound();

        $this->assertEquals(2, $battle->getRoundNumber());
        $this->assertEquals(BattleState::ACTIVE, $battle->getState());
    }

    public function test_start_new_round_fails_if_finished(): void
    {
        $char1 = $this->createMockCharacter(1, 100);
        $char2 = $this->createMockCharacter(2, 0);

        $battle = new Battle(1, 1, [$char1, $char2], $this->createDefaultMap());
        $battle->startResolving();
        $battle->finishResolving(); // FINISHED

        $this->expectException(Exception::class);
        $battle->startNewRound();
    }

    public function test_cannot_move_outside_bounds(): void
    {
        $char = $this->createMockCharacter(1, 100, 0, 0);
        $battle = new Battle(1, 1, [$char, $this->createMockCharacter(2)], new Map(2, 2));

        $action = new TurnAction(1, ActionType::MOVE, null, 0, 0, -1, 0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target cell is outside the map.');
        $battle->queueAction($action);
    }

    public function test_cannot_move_to_non_adjacent_tile(): void
    {
        $char = $this->createMockCharacter(1, 100, 0, 0);
        $battle = new Battle(1, 1, [$char, $this->createMockCharacter(2)], $this->createDefaultMap());

        $action = new TurnAction(1, ActionType::MOVE, null, 0, 0, 2, 0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target cell is not adjacent.');
        $battle->queueAction($action);
    }

    public function test_cannot_move_to_occupied_tile(): void
    {
        $char1 = $this->createMockCharacter(1, 100, 0, 0);
        $char2 = $this->createMockCharacter(2, 100, 1, 0);
        $battle = new Battle(1, 1, [$char1, $char2], $this->createDefaultMap());

        $action = new TurnAction(1, ActionType::MOVE, null, 0, 0, 1, 0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target cell is occupied.');
        $battle->queueAction($action);
    }

    public function test_cannot_attack_non_adjacent_opponent(): void
    {
        $char1 = $this->createMockCharacter(1, 100, 0, 0);
        $char2 = $this->createMockCharacter(2, 100, 5, 5);
        $battle = new Battle(1, 1, [$char1, $char2], $this->createDefaultMap());

        $action = new TurnAction(1, ActionType::ATTACK, \App\Domain\Battle\TargetZone::HEAD);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target is not adjacent.');
        $battle->queueAction($action);
    }

    public function test_cannot_attack_dead_opponent(): void
    {
        $char1 = $this->createMockCharacter(1, 100, 0, 0);
        $char2 = $this->createMockCharacter(2, 0, 1, 0); // Dead but adjacent
        $battle = new Battle(1, 1, [$char1, $char2], $this->createDefaultMap());

        $action = new TurnAction(1, ActionType::ATTACK, \App\Domain\Battle\TargetZone::HEAD);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target is already dead.');
        $battle->queueAction($action);
    }
}


