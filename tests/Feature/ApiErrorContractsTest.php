<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_loadout_returns_404_when_character_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/character/loadout')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Character not found.');
    }

    public function test_chat_private_returns_404_when_character_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/chat/private')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Character not found.');
    }

    public function test_chat_state_battle_returns_404_when_battle_is_missing(): void
    {
        $user = User::factory()->create();
        CharacterModel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/chat/state?chatType=battle&contextId=999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Battle not found.');
    }

    public function test_store_open_returns_404_when_store_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/store/999999/open')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Store not found.');
    }

    public function test_store_buy_returns_404_when_store_item_is_missing(): void
    {
        $user = User::factory()->create();
        CharacterModel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/store/buy', ['store_item_id' => 999999])
            ->assertStatus(404)
            ->assertJsonPath('error', 'Store item not found.');
    }

    public function test_fight_cancel_returns_404_when_character_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/fights/1/cancel')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Character not found.');
    }
}
