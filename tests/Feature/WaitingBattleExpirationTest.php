<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Battle\RoundExpirationHandler;
use App\Domain\Battle\BattleState;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingBattleExpirationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCharacter(int $locationId): CharacterModel
    {
        $user = User::factory()->create();
        return CharacterModel::create([
            'user_id' => $user->id,
            'name' => 'Char' . $user->id,
            'strength' => 1,
            'dexterity' => 1,
            'constitution' => 1,
            'wit' => 1,
            'hp' => 10,
            'max_hp' => 10,
            'location_id' => $locationId,
        ]);
    }

    public function test_waiting_battle_starts_on_timeout_with_two_players(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
        ]);

        $battle = BattleModel::create([
            'state' => BattleState::WAITING->value,
            'location_id' => $location->id,
            'round_number' => 1,
            'round_started_at' => now()->subSeconds(700),
            'round_duration_seconds' => 60,
            'start_timeout_seconds' => 600,
            'committed_character_ids' => [],
            'map_width' => 5,
            'map_height' => 3,
        ]);

        $c1 = $this->makeCharacter($location->id);
        $c2 = $this->makeCharacter($location->id);
        $battle->participants()->attach($c1->id, ['team' => 'blue']);
        $battle->participants()->attach($c2->id, ['team' => 'red']);

        app(RoundExpirationHandler::class)->handleExpiredRounds();

        $battle->refresh();
        $this->assertSame(BattleState::ACTIVE->value, $battle->state);
        $this->assertNotNull(FightMapModel::where('fight_id', $battle->id)->first());
    }

    public function test_waiting_battle_cancels_on_timeout_with_one_player(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
        ]);

        $battle = BattleModel::create([
            'state' => BattleState::WAITING->value,
            'location_id' => $location->id,
            'round_number' => 1,
            'round_started_at' => now()->subSeconds(700),
            'round_duration_seconds' => 60,
            'start_timeout_seconds' => 600,
            'committed_character_ids' => [],
            'map_width' => 5,
            'map_height' => 3,
        ]);

        $c1 = $this->makeCharacter($location->id);
        $battle->participants()->attach($c1->id, ['team' => 'blue']);

        app(RoundExpirationHandler::class)->handleExpiredRounds();

        $this->assertNull(BattleModel::find($battle->id));
    }
}
