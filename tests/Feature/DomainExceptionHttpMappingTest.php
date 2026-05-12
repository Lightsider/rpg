<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainExceptionHttpMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_for_missing_fight(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/fights/999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Fight not found.');
    }

    public function test_returns_403_for_non_participant_battle_log(): void
    {
        $location = LocationModel::factory()->create();
        $ownerUser = User::factory()->create();
        $ownerCharacter = CharacterModel::factory()->create([
            'user_id' => $ownerUser->id,
            'location_id' => $location->id,
        ]);

        $outsiderUser = User::factory()->create();
        CharacterModel::factory()->create([
            'user_id' => $outsiderUser->id,
            'location_id' => $location->id,
        ]);

        $battle = BattleModel::factory()->create([
            'location_id' => $location->id,
            'state' => 'active',
        ]);
        $battle->participants()->attach($ownerCharacter->id, ['team' => 'blue']);

        $this->actingAs($outsiderUser)
            ->getJson("/api/fights/{$battle->id}/log")
            ->assertStatus(403)
            ->assertJsonPath('error', 'You are not a participant in this ongoing fight.');
    }

    public function test_returns_409_when_changing_location_during_active_fight(): void
    {
        $from = LocationModel::factory()->create();
        $to = LocationModel::factory()->create();
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'location_id' => $from->id,
        ]);

        $battle = BattleModel::factory()->create([
            'location_id' => $from->id,
            'state' => 'active',
        ]);
        $battle->participants()->attach($character->id, ['team' => 'blue']);

        $this->actingAs($user)
            ->postJson("/api/locations/{$to->id}/enter")
            ->assertStatus(409)
            ->assertJsonPath('error', 'You cannot change locations while in a fight.');
    }

    public function test_returns_400_for_invalid_main_attack_count(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create(['user_id' => $user->id]);
        $battle = BattleModel::factory()->create(['state' => 'active']);
        $opponent = CharacterModel::factory()->create(['user_id' => User::factory()->create()->id]);
        $battle->participants()->attach([$character->id, $opponent->id]);

        $this->actingAs($user)
            ->postJson("/api/fights/{$battle->id}/actions", [
                'actions' => [
                    ['type' => 'attack', 'zone' => 'torso'],
                    ['type' => 'attack', 'zone' => 'torso'],
                    ['type' => 'attack', 'zone' => 'torso'],
                ],
            ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Maximum 2 main-hand attacks per round.');
    }
}

