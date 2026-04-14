<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombatApRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dagger_bearer_cannot_use_bonus_ap_for_extra_main_attack(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create(['user_id' => $user->id]);
        
        $dagger = ItemModel::factory()->create([
            'type' => 'offhand_weapon',
            'damage_type' => 'pierce',
            'offhand_ap_bonus' => 1
        ]);
        $character->update(['off_hand_id' => $dagger->id]);
        
        $battle = BattleModel::factory()->create(['state' => 'active']);
        $battle->participants()->attach($character->id);

        // Trying to submit 3 main attacks should fail
        $response = $this->actingAs($user)->postJson("/fights/{$battle->id}/actions", [
            'actions' => [
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'attack', 'zone' => 'torso']
            ]
        ]);

        $response->assertStatus(400); // DomainException
        $response->assertJsonPath('message', 'Maximum 2 main-hand attacks per round.');
    }

    public function test_shield_bearer_can_use_bonus_ap_for_block_but_not_attack(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create(['user_id' => $user->id]);
        
        $shield = ItemModel::factory()->create([
            'type' => 'shield',
            'defensive_ap_bonus' => 1
        ]);
        $character->update(['off_hand_id' => $shield->id]);
        
        $battle = BattleModel::factory()->create(['state' => 'active']);
        $battle->participants()->attach($character->id);

        // 3 attacks is invalid (max 2 main attacks)
        $response = $this->actingAs($user)->postJson("/fights/{$battle->id}/actions", [
            'actions' => [
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'attack', 'zone' => 'torso']
            ]
        ]);
        $response->assertStatus(400);

        // 2 attacks + 2 blocks is valid for shield
        $response = $this->actingAs($user)->postJson("/fights/{$battle->id}/actions", [
            'actions' => [
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'attack', 'zone' => 'torso'],
                ['type' => 'block', 'zone' => 'torso'],
                ['type' => 'block', 'zone' => 'head']
            ]
        ]);
        $response->assertStatus(200);
    }
}
