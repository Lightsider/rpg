<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Battle\BattleLobbyService;
use App\Domain\Battle\BattleState;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleLobbyStartTest extends TestCase
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

    public function test_battle_starts_when_max_participants_reached(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
            'max_players' => 2,
            'start_timeout_seconds' => 600,
        ]);

        $c1 = $this->makeCharacter($location->id);
        $c2 = $this->makeCharacter($location->id);

        $service = app(BattleLobbyService::class);
        $battleId = $service->createBattle(app(\App\Domain\Character\Repositories\CharacterRepositoryInterface::class)->findByUserId($c1->user_id));

        $battle = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class)->findById($battleId);
        $this->assertSame(BattleState::WAITING, $battle->getState());

        $service->joinBattle($battle, app(\App\Domain\Character\Repositories\CharacterRepositoryInterface::class)->findByUserId($c2->user_id));

        $battle = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class)->findById($battleId);
        $this->assertSame(BattleState::ACTIVE, $battle->getState());
    }
}
