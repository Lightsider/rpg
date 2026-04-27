<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoHandedWeaponEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_equip_2h_weapon_when_offhand_is_not_empty(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
        ]);

        $twoHandedAxe = ItemModel::factory()->create([
            'name' => '2H Axe',
            'type' => 'weapon',
            'is_two_handed' => true,
        ]);

        $dagger = ItemModel::factory()->create([
            'name' => 'Dagger',
            'type' => 'offhand_weapon',
            'is_two_handed' => false,
        ]);

        // Put items in backpack
        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $twoHandedAxe->id, 'quantity' => 1]);
        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $dagger->id, 'quantity' => 1]);

        // Equip dagger in offhand
        $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $dagger->id,
            'slot' => 'off_hand'
        ])->assertStatus(200);

        $character->refresh();
        $this->assertEquals($dagger->id, $character->off_hand_id);

        // Try to equip 2H axe in main hand
        $response = $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $twoHandedAxe->id,
            'slot' => 'main_hand'
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Offhand must be empty to equip a 2-handed weapon.']);

        $character->refresh();
        $this->assertNotEquals($twoHandedAxe->id, $character->weapon_id);
        $this->assertEquals($dagger->id, $character->off_hand_id);
    }

    public function test_cannot_equip_offhand_when_2h_weapon_is_equipped(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
        ]);

        $twoHandedAxe = ItemModel::factory()->create([
            'name' => '2H Axe',
            'type' => 'weapon',
            'is_two_handed' => true,
        ]);

        $dagger = ItemModel::factory()->create([
            'name' => 'Dagger',
            'type' => 'offhand_weapon',
            'is_two_handed' => false,
        ]);

        // Put items in backpack
        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $twoHandedAxe->id, 'quantity' => 1]);
        CharacterItemModel::create(['character_id' => $character->id, 'item_id' => $dagger->id, 'quantity' => 1]);

        // Equip 2H axe in main hand
        $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $twoHandedAxe->id,
            'slot' => 'main_hand'
        ])->assertStatus(200);

        // Try to equip dagger in offhand
        $response = $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $dagger->id,
            'slot' => 'off_hand'
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot equip offhand item when a 2-handed weapon is equipped.']);

        $character->refresh();
        $this->assertNull($character->off_hand_id);
    }
}
