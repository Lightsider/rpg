<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Battle\QueueAttackAction;
use App\Application\Battle\QueueMoveAction;
use App\Domain\Battle\Battle;
use App\Domain\Battle\Map;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Domain\Character\Character;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BattleActionQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeCharacter(int $id, int $x, int $y): Character
    {
        $weapon = new Weapon(1, 'Sword', 1, 1, DamageType::SLASHING, 0.0, 0, 0.0);
        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $id,
            userId: $id,
            name: "Char{$id}",
            strength: 1,
            agility: 1,
            constitution: 1,
            wit: 1,
            maxHp: 10,
            currentHp: 10,
            equipment: $equipment,
            x: $x,
            y: $y
        );
    }

    private function makeBattle(Character $a, Character $b): Battle
    {
        return new Battle(
            id: 1,
            locationId: 1,
            participants: [$a->getId() => $a, $b->getId() => $b],
            map: Map::default()
        );
    }

    public function test_queue_attack_spends_ap_and_queues_action(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $attacker = $this->makeCharacter(1, 0, 0);
        $defender = $this->makeCharacter(2, 1, 0);
        $battle = $this->makeBattle($attacker, $defender);

        $repo = Mockery::mock(BattleRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn($battle);
        $repo->shouldReceive('save')->andReturn(1);

        $service = new QueueAttackAction($repo);
        $service->execute(1, 1, 'head');

        $this->assertCount(1, $battle->getQueuedActions());
        $this->assertSame(2, $attacker->getCurrentActionPoints());
    }

    public function test_queue_attack_persists_target_id(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $attacker = $this->makeCharacter(1, 0, 0);
        $defender = $this->makeCharacter(2, 1, 0);
        $battle = $this->makeBattle($attacker, $defender);

        $repo = Mockery::mock(BattleRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn($battle);
        $repo->shouldReceive('save')->andReturn(1);

        $service = new QueueAttackAction($repo);
        $service->execute(1, 1, 'head', 2);

        $this->assertCount(1, $battle->getQueuedActions());
        $queued = $battle->getQueuedActions()[0];
        $this->assertSame(2, $queued->getTargetId());
    }

    public function test_queue_move_rejects_duplicate_blocks(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $mover = $this->makeCharacter(1, 0, 0);
        $other = $this->makeCharacter(2, 1, 0);
        $battle = $this->makeBattle($mover, $other);

        $repo = Mockery::mock(BattleRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn($battle);

        $service = new QueueMoveAction($repo);

        $this->expectException(DomainException::class);
        $service->execute(1, 1, 0, 1, ['head', 'head']);
    }
}
