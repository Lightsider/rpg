<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_is_unequipped_when_stats_are_reduced_below_requirements(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'strength' => 7,
            'dexterity' => 1,
            'constitution' => 4,
            'wit' => 4,
        ]);

        $heavyArmor = ItemModel::factory()->create([
            'name' => 'Heavy Plate',
            'type' => 'armor',
            'armor_subtype' => 'body',
            'required_strength' => 7,
        ]);

        // Put item in backpack first
        CharacterItemModel::create([
            'character_id' => $character->id,
            'item_id' => $heavyArmor->id,
            'quantity' => 1
        ]);

        // Equip it
        $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $heavyArmor->id,
            'slot' => 'chest'
        ])->assertStatus(200);

        $character->refresh();
        $this->assertEquals($heavyArmor->id, $character->chest_id);

        // Reduce strength below requirement (Total must stay 16)
        $response = $this->actingAs($user)->putJson('/api/character/loadout', [
            'strength' => 4,
            'dexterity' => 4,
            'constitution' => 4,
            'wit' => 4,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['unequipped' => ['Heavy Plate']]);

        $character->refresh();
        $this->assertNull($character->chest_id);

        // Check if item is back in backpack
        $backpackEntry = CharacterItemModel::where('character_id', $character->id)
            ->where('item_id', $heavyArmor->id)
            ->first();
        
        $this->assertNotNull($backpackEntry);
        $this->assertEquals(1, $backpackEntry->quantity);
    }
}
