<?php

namespace Tests\Unit\Application\Battle;

use App\Application\Battle\BotFillingService;
use App\Application\Battle\NpcFactory;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
use App\Domain\Npc\NpcCombatant;
use App\Domain\Npc\NpcTemplate;
use App\Domain\Npc\NpcType;
use App\Services\TeamAssigner;
use PHPUnit\Framework\TestCase;
use Mockery;

class BotFillingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_it_fills_battle_with_bots()
    {
        $battleRepo = Mockery::mock(BattleRepositoryInterface::class);
        $templateRepo = Mockery::mock(NpcTemplateRepositoryInterface::class);
        $itemRepo = Mockery::mock(ItemRepositoryInterface::class);
        $npcFactory = Mockery::mock(NpcFactory::class);
        $teamAssigner = Mockery::mock(TeamAssigner::class);

        $service = new BotFillingService(
            $battleRepo,
            $templateRepo,
            $itemRepo,
            $npcFactory,
            $teamAssigner
        );

        $battle = new Battle(
            id: 1,
            locationId: 1,
            participants: [1 => Mockery::mock(\App\Domain\Character\Character::class)],
            map: Map::default(),
            maxParticipants: 4,
            state: BattleState::WAITING,
            fillWithBots: true
        );

        $template = new NpcTemplate(
            id: 1,
            name: 'Humanoid',
            type: NpcType::HUMANOID,
            strength: 4,
            agility: 4,
            constitution: 4,
            wit: 4,
            level: 1,
            behaviorModel: 'aggressive',
            equipmentItemIds: []
        );
        $templateRepo->shouldReceive('findAll')->andReturn([$template]);
        $itemRepo->shouldReceive('findAll')->andReturn([]);

        $idCounter = 1000;
        $battleRepo->shouldReceive('generateNpcCombatantId')->andReturnUsing(function() use (&$idCounter) {
            return $idCounter++;
        });
        $teamAssigner->shouldReceive('assign')->andReturn('Team A');

        $npcFactory->shouldReceive('createFromTemplate')->andReturnUsing(function($t, $id) {
            $npc = Mockery::mock(NpcCombatant::class);
            $npc->shouldReceive('getId')->andReturn($id);
            $npc->shouldReceive('setName');
            $npc->shouldReceive('getStrength')->andReturn(4);
            $npc->shouldReceive('getAgility')->andReturn(4);
            $npc->shouldReceive('getConstitution')->andReturn(4);
            $npc->shouldReceive('getWit')->andReturn(4);
            $npc->shouldReceive('getEquipment')->andReturn(new \App\Domain\Equipment\Equipment());
            $npc->shouldReceive('initializeAdArmor');
            $npc->shouldReceive('restoreHp');
            $npc->shouldReceive('isNpc')->andReturn(true);
            return $npc;
        });
        
        $battleRepo->shouldReceive('save')->once();

        $service->fillBattle($battle);

        $this->assertCount(4, $battle->getParticipants());
    }
}
