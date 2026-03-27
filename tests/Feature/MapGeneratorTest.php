<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Character\Character;
use App\Domain\Equipment\Equipment;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Services\MapGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function makeCharacter(int $id, int $locationId): Character
    {
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
            equipment: new Equipment(),
            locationId: $locationId
        );
    }

    public function test_map_height_grows_with_participants(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
        ]);

        $battleModel = BattleModel::create([
            'state' => BattleState::WAITING->value,
            'location_id' => $location->id,
            'round_number' => 1,
            'round_started_at' => now(),
            'round_duration_seconds' => 60,
            'committed_character_ids' => [],
            'map_width' => Map::DEFAULT_WIDTH,
            'map_height' => Map::DEFAULT_HEIGHT,
        ]);

        $participants = [];
        for ($i = 1; $i <= 4; $i++) {
            $user = User::factory()->create();
            $character = CharacterModel::create([
                'user_id' => $user->id,
                'name' => "Char{$i}",
                'strength' => 1,
                'dexterity' => 1,
                'constitution' => 1,
                'wit' => 1,
                'hp' => 10,
                'max_hp' => 10,
                'location_id' => $location->id,
            ]);
            $participants[$character->id] = $this->makeCharacter($character->id, $location->id);
            $battleModel->participants()->attach($character->id, ['team' => $i % 2 === 0 ? 'red' : 'blue']);
        }

        $battle = new Battle(
            id: $battleModel->id,
            locationId: $location->id,
            participants: $participants,
            map: Map::default(),
            state: BattleState::WAITING
        );
        foreach ($participants as $participant) {
            $battle->assignTeam($participant->getId(), $participant->getId() % 2 === 0 ? 'red' : 'blue');
        }

        $generator = app(MapGenerator::class);
        $generator->generateForFight($battle);

        $map = FightMapModel::where('fight_id', $battleModel->id)->firstOrFail();
        $this->assertSame(Map::DEFAULT_HEIGHT + 2, (int) $map->height);
    }
}
