<?php

namespace Tests\Feature;

use App\Domain\Battle\BattleReward;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_rewards_are_saved_and_returned_in_api(): void
    {
        $location = LocationModel::factory()->create();

        $user1 = User::factory()->create();
        $char1 = CharacterModel::factory()->create([
            'user_id' => $user1->id,
            'location_id' => $location->id,
            'hp' => 100,
            'max_hp' => 100,
            'experience' => 0,
            'currency_copper' => 0,
        ]);

        $user2 = User::factory()->create();
        $char2 = CharacterModel::factory()->create([
            'user_id' => $user2->id,
            'location_id' => $location->id,
            'hp' => 0, // dead => loser
            'max_hp' => 100,
            'experience' => 0,
            'currency_copper' => 0,
        ]);

        // Manually create a finished battle with rewards (simulating what the Domain does in tests)
        $battle = BattleModel::factory()->create([
            'location_id' => $location->id,
            'state' => 'finished',
            'winner_ids' => [$char1->id],
            'rewards' => [
                $char1->id => (new BattleReward(xp: 100, copper: 50, items: []))->jsonSerialize(),
                $char2->id => (new BattleReward(xp: 20, copper: 10, items: []))->jsonSerialize(),
            ],
        ]);

        $battle->participants()->sync([
            $char1->id => ['team' => null],
            $char2->id => ['team' => null],
        ]);

        // Verify API Returns Rewards
        $response = $this->actingAs($user1)->getJson("/api/fights/{$battle->id}");
        $response->assertStatus(200);
        
        $json = $response->json();
        
        $this->assertArrayHasKey('rewards', $json);
        $this->assertArrayHasKey($char1->id, $json['rewards']);
        $this->assertArrayHasKey($char2->id, $json['rewards']);

        $this->assertEquals(100, $json['rewards'][$char1->id]['xp']);
        $this->assertEquals(50, $json['rewards'][$char1->id]['copper']);
        $this->assertEquals(20, $json['rewards'][$char2->id]['xp']);
        $this->assertEquals(10, $json['rewards'][$char2->id]['copper']);
    }
}
