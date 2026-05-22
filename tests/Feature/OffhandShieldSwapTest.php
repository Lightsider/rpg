<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffhandShieldSwapTest extends TestCase
{
    use RefreshDatabase;

    public function test_swapping_offhand_shield_returns_previous_item_once(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
        ]);

        $shieldOne = ItemModel::factory()->create([
            'name' => 'Shield One',
            'type' => 'shield',
            'armor_subtype' => 'off_hand',
        ]);

        $shieldTwo = ItemModel::factory()->create([
            'name' => 'Shield Two',
            'type' => 'shield',
            'armor_subtype' => 'off_hand',
        ]);

        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $shieldOne->id, 'quantity' => 1]);
        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $shieldTwo->id, 'quantity' => 1]);

        $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $shieldOne->id,
            'slot' => 'off_hand',
        ])->assertStatus(200);

        $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $shieldTwo->id,
            'slot' => 'off_hand',
        ])->assertStatus(200);

        $character->refresh();
        $this->assertSame($shieldTwo->id, (int) $character->off_hand_id);

        $firstShieldEntry = CharacterItemModel::query()
            ->where('character_id', $character->id)
            ->where('item_id', $shieldOne->id)
            ->first();
        $this->assertNotNull($firstShieldEntry);
        $this->assertSame(1, (int) $firstShieldEntry->quantity);

        $secondShieldEntry = CharacterItemModel::query()
            ->where('character_id', $character->id)
            ->where('item_id', $shieldTwo->id)
            ->first();
        $this->assertNull($secondShieldEntry);
    }
}

