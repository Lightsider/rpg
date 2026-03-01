<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Character\Character;
use App\Domain\Weapon\Weapon;
use App\Domain\Weapon\DamageType;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class BattleTest extends TestCase
{
    private function createMockCharacter(int $id, int $hp = 100): Character
    {
        $weapon = new Weapon(1, "Sword", 10, 20, DamageType::SLASHING, 0.9, 0.1);
        return new Character(
            id: $id,
            name: "Char $id",
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: $hp,
            weapon: $weapon
        );
    }

    public function test_initial_state_is_active(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

        $this->assertEquals(BattleState::ACTIVE, $battle->getState());
        $this->assertEquals(1, $battle->getRoundNumber());
        $this->assertFalse($battle->isFinished());
    }

    public function test_start_resolving_transitions_to_resolving(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

        $battle->startResolving();
        $this->assertEquals(BattleState::RESOLVING, $battle->getState());
    }

    public function test_start_resolving_fails_if_already_resolving(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

        $battle->startResolving();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Round can resolve ONLY if state is ACTIVE.');
        $battle->startResolving();
    }

    public function test_finish_resolving_transitions_back_to_active(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

        $battle->startResolving();
        $battle->finishResolving();

        $this->assertEquals(BattleState::ACTIVE, $battle->getState());
    }

    public function test_finish_resolving_transitions_to_finished_if_one_left_alive(): void
    {
        $char1 = $this->createMockCharacter(1, 100);
        $char2 = $this->createMockCharacter(2, 0); // Already dead

        $battle = new Battle(1, [$char1, $char2]);

        $battle->startResolving();
        $battle->finishResolving();

        $this->assertEquals(BattleState::FINISHED, $battle->getState());
        $this->assertTrue($battle->isFinished());
    }

    public function test_cannot_commit_if_resolving(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

        $battle->startResolving();

        $this->expectException(Exception::class);
        $battle->commitCharacter(1);
    }

    public function test_start_new_round_resets_state(): void
    {
        $battle = new Battle(1, [
            $this->createMockCharacter(1),
            $this->createMockCharacter(2)
        ]);

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

        $battle = new Battle(1, [$char1, $char2]);
        $battle->startResolving();
        $battle->finishResolving(); // FINISHED

        $this->expectException(Exception::class);
        $battle->startNewRound();
    }
}
